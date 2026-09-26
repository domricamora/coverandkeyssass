<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\Request;

/** Purchase orders (Phase 18): draft → ordered → received (partial allowed) → stock receipts at weighted cost. */
class PurchaseOrderController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'inventory.view');

        return \Inertia\Inertia::render('Inventory/PurchaseOrders', [
            'orders' => PurchaseOrder::query()->with(['supplier', 'location'])->latest()->paginate(20)->through(fn (PurchaseOrder $po) => [
                'reference' => $po->reference,
                'when' => $po->created_at->format('M j').($po->expected_on ? ' · due '.$po->expected_on->format('M j') : ''),
                'supplier' => $po->supplier?->name,
                'location' => $po->location?->name,
                'status' => $po->status,
                'total' => '₱'.number_format((float) $po->total, 2),
                'href' => route('inventory.purchase-orders.show', $po->reference),
            ]),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => [$s->id, $s->name]),
            'locations' => StockLocation::query()->orderBy('name')->get(['id', 'name'])->map(fn ($l) => [$l->id, $l->name]),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit'])->map(fn ($i) => [$i->id, $i->name.' ('.$i->unit.')']),
            'tabs' => InventoryController::tabs('po'),
            'can' => ['buy' => $request->user()->hasPermissionTo('purchasing.manage')],
            'urls' => ['store' => route('inventory.purchase-orders.store')],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTo($request, 'purchasing.manage');

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'stock_location_id' => ['required', 'integer'],
            'expected_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $po = $this->inventory->createPurchaseOrder(
            Supplier::query()->findOrFail($validated['supplier_id']),
            StockLocation::query()->findOrFail($validated['stock_location_id']),
            $validated['lines'], $request->user(), $validated['expected_on'] ?? null, $validated['notes'] ?? null,
        );

        return redirect()->route('inventory.purchase-orders.show', $po->reference)->with('success', 'Purchase order '.$po->reference.' drafted.');
    }

    public function show(Request $request, string $po)
    {
        $this->authorizeTo($request, 'inventory.view');

        $po = $this->find($po)->load(['supplier', 'location', 'lines.item']);
        $user = $request->user();
        $buy = $user->hasPermissionTo('purchasing.manage');

        return \Inertia\Inertia::render('Inventory/PurchaseOrder', [
            'po' => [
                'reference' => $po->reference,
                'status' => $po->status,
                'summary' => $po->supplier?->name.' → '.$po->location?->name.($po->expected_on ? ' · expected '.$po->expected_on->format('M j') : ''),
                'total' => '₱'.number_format((float) $po->total, 2),
                'lines' => $po->lines->map(fn ($l) => [
                    'id' => $l->id,
                    'item' => $l->item?->name,
                    'ordered' => $l->item?->qty($l->quantity),
                    'received' => $l->item?->qty($l->received_quantity),
                    'cost' => '₱'.number_format((float) $l->unit_cost, 2),
                    'line' => '₱'.number_format((float) $l->quantity * (float) $l->unit_cost, 2),
                    'outstanding' => (float) $l->outstanding(),
                ]),
            ],
            'can' => [
                'order' => $buy && $po->status === 'draft',
                'cancel' => $buy && in_array($po->status, ['draft', 'ordered'], true),
                'receive' => $user->hasPermissionTo('inventory.manage') && in_array($po->status, ['ordered', 'partially_received'], true),
            ],
            'tabs' => InventoryController::tabs('po'),
            'urls' => [
                'index' => route('inventory.purchase-orders.index'),
                'order' => route('inventory.purchase-orders.order', $po->reference),
                'cancel' => route('inventory.purchase-orders.cancel', $po->reference),
                'receive' => route('inventory.purchase-orders.receive', $po->reference),
            ],
        ]);
    }

    public function order(Request $request, string $po)
    {
        $this->authorizeTo($request, 'purchasing.manage');
        $this->inventory->markOrdered($this->find($po));

        return back()->with('success', 'Marked as ordered.');
    }

    public function receive(Request $request, string $po)
    {
        $this->authorizeTo($request, 'inventory.manage');

        $received = $request->validate(['received' => ['required', 'array'], 'received.*' => ['nullable', 'numeric', 'min:0']])['received'];
        $order = $this->inventory->receivePurchaseOrder($this->find($po), $received, $request->user());

        return back()->with('success', $order->status === PurchaseOrder::RECEIVED ? 'Fully received.' : 'Partially received.');
    }

    public function cancel(Request $request, string $po)
    {
        $this->authorizeTo($request, 'purchasing.manage');
        $this->inventory->cancelPurchaseOrder($this->find($po));

        return back()->with('success', 'Purchase order cancelled.');
    }

    private function find(string $reference): PurchaseOrder
    {
        return PurchaseOrder::query()->where('reference', $reference)->firstOrFail();
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
