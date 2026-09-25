<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\MenuItemIngredient;
use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Notifications\LowStock;
use App\Modules\Inventory\Support\Unit;
use App\Modules\Ordering\Models\Order;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inventory (Phase 18). Every quantity change is one move(): it locks the
 * item row and then the level row (always in that order, so concurrent
 * moves never deadlock), writes a signed ledger line with the balance
 * after, keeps a weighted-average cost on receipts and raises a low-stock
 * notification when the item's total crosses its reorder level.
 *
 * Stock may not go negative, except for sales: the kitchen is never
 * blocked by a miscount — the level shows negative until it is counted.
 */
class InventoryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function receive(InventoryItem $item, StockLocation $location, float $quantity, float $unitCost, ?User $by, ?string $reference = null): StockMovement
    {
        $this->positive($quantity);

        return $this->move($item, $location, 'receipt', $quantity, $by, unitCost: $unitCost, reference: $reference);
    }

    public function issue(InventoryItem $item, StockLocation $location, float $quantity, ?User $by, ?string $notes = null): StockMovement
    {
        $this->positive($quantity);

        return $this->move($item, $location, 'issue', -$quantity, $by, notes: $notes);
    }

    public function waste(InventoryItem $item, StockLocation $location, float $quantity, string $reason, ?User $by): StockMovement
    {
        $this->positive($quantity);

        return $this->move($item, $location, 'waste', -$quantity, $by, notes: $reason);
    }

    /** Stock count: book the difference between the counted and recorded quantity. */
    public function count(InventoryItem $item, StockLocation $location, float $counted, ?User $by): ?StockMovement
    {
        if ($counted < 0) {
            $this->fail('quantity', 'A count cannot be negative.');
        }

        $current = (float) (StockLevel::query()->where('inventory_item_id', $item->id)->where('stock_location_id', $location->id)->value('quantity') ?? 0);
        $difference = round($counted - $current, 3);

        return $difference == 0.0 ? null : $this->move($item, $location, 'adjustment', $difference, $by, notes: 'Stock count', allowNegative: true);
    }

    /** @return array{0: StockMovement, 1: StockMovement} */
    public function transfer(InventoryItem $item, StockLocation $from, StockLocation $to, float $quantity, ?User $by): array
    {
        $this->positive($quantity);

        if ($from->is($to)) {
            $this->fail('to_location_id', 'Pick a different destination.');
        }

        return DB::transaction(fn () => [
            $this->move($item, $from, 'transfer_out', -$quantity, $by, reference: $to->name),
            $this->move($item, $to, 'transfer_in', $quantity, $by, reference: $from->name),
        ]);
    }

    // ------------------------------------------------------------------
    // Purchase orders
    // ------------------------------------------------------------------

    /** @param list<array{inventory_item_id: int, quantity: float, unit_cost: float}> $lines */
    public function createPurchaseOrder(Supplier $supplier, StockLocation $location, array $lines, User $by, ?string $expectedOn = null, ?string $notes = null): PurchaseOrder
    {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['quantity'] ?? 0) > 0));

        if ($lines === []) {
            $this->fail('lines', 'Add at least one line.');
        }

        return DB::transaction(function () use ($supplier, $location, $lines, $by, $expectedOn, $notes) {
            $po = PurchaseOrder::create([
                'supplier_id' => $supplier->id,
                'stock_location_id' => $location->id,
                'reference' => PurchaseOrder::newReference(),
                'expected_on' => $expectedOn,
                'notes' => $notes,
                'created_by' => $by->id,
            ]);

            foreach ($lines as $line) {
                $item = InventoryItem::query()->findOrFail((int) $line['inventory_item_id']);
                $po->lines()->create(['inventory_item_id' => $item->id, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'] ?? $item->cost_per_unit]);
            }

            $po->forceFill(['total' => round((float) $po->lines()->sum(DB::raw('quantity * unit_cost')), 2)])->save();

            return $po;
        });
    }

    public function markOrdered(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::DRAFT) {
            $this->fail('status', 'Only drafts can be sent to the supplier.');
        }

        $po->forceFill(['status' => PurchaseOrder::ORDERED, 'ordered_at' => now()])->save();

        return $po;
    }

    /** @param array<int, float|string> $received line id => quantity received now */
    public function receivePurchaseOrder(PurchaseOrder $po, array $received, User $by): PurchaseOrder
    {
        if (! in_array($po->status, [PurchaseOrder::ORDERED, PurchaseOrder::PARTIAL], true)) {
            $this->fail('status', 'Only ordered purchase orders can be received.');
        }

        return DB::transaction(function () use ($po, $received, $by) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            $any = false;

            foreach ($po->lines()->with('item')->get() as $line) {
                $quantity = round((float) ($received[$line->id] ?? 0), 3);

                if ($quantity <= 0) {
                    continue;
                }

                if ($quantity > $line->outstanding() + 0.0005) {
                    $this->fail('received', 'Receiving more '.$line->item->name.' than was ordered ('.$line->item->qty($line->outstanding()).' outstanding).');
                }

                $this->move($line->item, $po->location, 'receipt', $quantity, $by, unitCost: (float) $line->unit_cost, reference: $po->reference);
                $line->forceFill(['received_quantity' => (float) $line->received_quantity + $quantity])->save();
                $any = true;
            }

            if (! $any) {
                $this->fail('received', 'Enter the quantities received.');
            }

            $done = $po->lines()->get()->every(fn ($l) => $l->outstanding() <= 0);
            $po->forceFill(['status' => $done ? PurchaseOrder::RECEIVED : PurchaseOrder::PARTIAL, 'received_at' => $done ? now() : null])->save();

            $this->audit->log('purchase_order.received', $po, null, ['complete' => $done]);

            return $po;
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $po): void
    {
        if (! in_array($po->status, [PurchaseOrder::DRAFT, PurchaseOrder::ORDERED], true)) {
            $this->fail('status', 'Received goods cannot be cancelled — record a return (issue) instead.');
        }

        $po->forceFill(['status' => PurchaseOrder::CANCELLED])->save();
    }

    // ------------------------------------------------------------------
    // Recipes and food sales
    // ------------------------------------------------------------------

    public function setIngredient(MenuItem $menuItem, InventoryItem $item, float $quantity, string $enteredUnit): MenuItemIngredient
    {
        $this->positive($quantity);

        try {
            $converted = Unit::convert($quantity, $enteredUnit, $item->unit);
        } catch (\InvalidArgumentException) {
            $this->fail('unit', $item->name.' is stocked in '.$item->unit.' — use '.implode(', ', Unit::compatibleWith($item->unit)).'.');
        }

        return MenuItemIngredient::updateOrCreate(
            ['menu_item_id' => $menuItem->id, 'inventory_item_id' => $item->id],
            ['quantity' => round($converted, 3), 'entered_unit' => $enteredUnit, 'entered_quantity' => $quantity],
        );
    }

    /** An accepted order uses its ingredients at the restaurant's stock location. Idempotent per order line. */
    public function consumeOrder(Order $order): void
    {
        $this->forOrderIngredients($order, function (InventoryItem $item, StockLocation $location, float $quantity, string $key) use ($order): void {
            $this->move($item, $location, 'sale', -$quantity, null, reference: $order->reference, sourceKey: $key, allowNegative: true);
        });
    }

    /** Cancelled after acceptance (before cooking): put the ingredients back. */
    public function restoreOrder(Order $order): void
    {
        $this->forOrderIngredients($order, function (InventoryItem $item, StockLocation $location, float $quantity, string $key) use ($order): void {
            if (StockMovement::query()->where('source_key', $key)->exists()) {
                $this->move($item, $location, 'sale_return', $quantity, null, reference: $order->reference, sourceKey: $key.':return');
            }
        });
    }

    // ------------------------------------------------------------------

    public function move(InventoryItem $item, StockLocation $location, string $type, float $quantity, ?User $by, ?float $unitCost = null, ?string $reference = null, ?string $notes = null, ?string $sourceKey = null, bool $allowNegative = false): StockMovement
    {
        return DB::transaction(function () use ($item, $location, $type, $quantity, $by, $unitCost, $reference, $notes, $sourceKey, $allowNegative) {
            if ($sourceKey && ($existing = StockMovement::query()->where('source_key', $sourceKey)->first())) {
                return $existing;
            }

            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            StockLevel::query()->firstOrCreate(['inventory_item_id' => $item->id, 'stock_location_id' => $location->id], ['quantity' => 0]);
            $level = StockLevel::query()->where('inventory_item_id', $item->id)->where('stock_location_id', $location->id)->lockForUpdate()->firstOrFail();

            $totalBefore = (float) StockLevel::query()->where('inventory_item_id', $item->id)->sum('quantity');
            $balance = round((float) $level->quantity + $quantity, 3);

            if ($balance < 0 && ! $allowNegative) {
                $this->fail('quantity', 'Only '.$item->qty($level->quantity).' of '.$item->name.' at '.$location->name.'.');
            }

            if ($type === 'receipt' && $unitCost !== null) {
                $onHand = max(0, $totalBefore);
                $item->forceFill(['cost_per_unit' => round(($onHand * (float) $item->cost_per_unit + $quantity * $unitCost) / ($onHand + $quantity), 4)])->save();
            }

            $level->forceFill(['quantity' => $balance])->save();

            $movement = StockMovement::create([
                'inventory_item_id' => $item->id,
                'stock_location_id' => $location->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'unit_cost' => $unitCost ?? $item->cost_per_unit,
                'reference' => $reference,
                'source_key' => $sourceKey,
                'notes' => $notes,
                'user_id' => $by?->id,
            ]);

            $totalAfter = $totalBefore + $quantity;
            if ((float) $item->reorder_level > 0 && $totalBefore > (float) $item->reorder_level && $totalAfter <= (float) $item->reorder_level) {
                DB::afterCommit(fn () => $this->alertLowStock($item, $totalAfter));
            }

            return $movement;
        });
    }

    private function forOrderIngredients(Order $order, \Closure $callback): void
    {
        $locationId = $order->restaurant?->stock_location_id;

        if (! $locationId || ! ($location = StockLocation::query()->find($locationId))) {
            return; // restaurant not linked to inventory
        }

        foreach ($order->items()->whereNotNull('menu_item_id')->get() as $line) {
            foreach (MenuItemIngredient::query()->where('menu_item_id', $line->menu_item_id)->with('item')->get() as $ingredient) {
                $callback($ingredient->item, $location, round((float) $ingredient->quantity * $line->quantity, 3), 'order:'.$order->id.':line:'.$line->id.':item:'.$ingredient->inventory_item_id);
            }
        }
    }

    private function alertLowStock(InventoryItem $item, float $total): void
    {
        $tenant = app(TenantContext::class)->tenant();

        $tenant?->users()->wherePivot('status', 'active')->get()
            ->filter(fn (User $user) => $user->hasPermissionTo('inventory.manage', $tenant->id))
            ->each(fn (User $user) => $user->notify(new LowStock($item, $total)));
    }

    private function positive(float $quantity): void
    {
        if ($quantity <= 0) {
            $this->fail('quantity', 'Enter a quantity above zero.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
