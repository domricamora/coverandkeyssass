<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Support\Unit;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Stock catalogue, levels and movements (Phase 18). */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'inventory.view');

        $items = InventoryItem::query()->with(['category', 'levels'])
            ->when($request->query('category'), fn ($q, $c) => $q->where('inventory_category_id', $c))
            ->when($request->boolean('low'), fn ($q) => $q->lowStock())
            ->orderBy('name')->get();

        $user = $request->user();

        return \Inertia\Inertia::render('Inventory/Index', [
            'items' => $items->map(function (InventoryItem $i) {
                $total = $i->totalStock();

                return [
                    'id' => $i->id,
                    'name' => $i->name,
                    'sku' => $i->sku,
                    'category' => $i->category?->name,
                    'on_hand' => $i->qty($total),
                    'low' => (float) $i->reorder_level > 0 && $total <= (float) $i->reorder_level,
                    'reorder' => (float) $i->reorder_level > 0 ? $i->qty($i->reorder_level) : null,
                    'cost' => \App\Support\Currency::symbol().number_format((float) $i->cost_per_unit, 2).'/'.$i->unit,
                    'active' => (bool) $i->is_active,
                    'href' => route('inventory.items.show', $i->id),
                ];
            }),
            'lowCount' => InventoryItem::query()->lowStock()->count(),
            'filters' => ['category' => $request->query('category'), 'low' => $request->boolean('low') ? 1 : null],
            'lists' => [
                'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [$c->id, $c->name]),
                'locations' => StockLocation::query()->orderBy('name')->pluck('name'),
                'suppliers' => Supplier::query()->orderBy('name')->pluck('name'),
            ],
            'units' => Unit::all(),
            'tabs' => self::tabs('stock'),
            'can' => ['manage' => $user->hasPermissionTo('inventory.manage'), 'buy' => $user->hasPermissionTo('purchasing.manage')],
            'urls' => [
                'self' => route('inventory.index'),
                'addItem' => route('inventory.items.store'),
                'addCategory' => route('inventory.categories.store'),
                'addLocation' => route('inventory.locations.store'),
                'addSupplier' => route('inventory.suppliers.store'),
            ],
        ]);
    }

    public function storeItem(Request $request)
    {
        $this->authorizeTo($request, 'inventory.manage');
        $tenantId = app(TenantContext::class)->id();

        $request->merge(['sku' => strtoupper(trim((string) $request->input('sku')))]);
        $item = InventoryItem::create($request->validate([
            'sku' => ['required', 'alpha_dash', 'max:40', Rule::unique('inventory_items')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:160'],
            'unit' => ['required', Rule::in(Unit::all())],
            'inventory_category_id' => ['nullable', 'integer', Rule::exists('inventory_categories', 'id')->where('tenant_id', $tenantId)],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
        ]) + ['reorder_level' => 0, 'cost_per_unit' => 0]);

        return redirect()->route('inventory.items.show', $item->id)->with('success', $item->name.' added.');
    }

    public function updateItem(Request $request, string $item)
    {
        $this->authorizeTo($request, 'inventory.manage');
        $item = InventoryItem::query()->findOrFail($item);
        $tenantId = app(TenantContext::class)->id();

        $item->update($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'inventory_category_id' => ['nullable', 'integer', Rule::exists('inventory_categories', 'id')->where('tenant_id', $tenantId)],
            'reorder_level' => ['required', 'numeric', 'min:0'],
        ]) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Item updated.');
    }

    public function showItem(Request $request, string $item)
    {
        $this->authorizeTo($request, 'inventory.view');
        $item = InventoryItem::query()->with(['category', 'levels.location'])->findOrFail($item);

        return \Inertia\Inertia::render('Inventory/Item', [
            'item' => [
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'summary' => $item->qty($item->totalStock()).' on hand · avg '.\App\Support\Currency::symbol().number_format((float) $item->cost_per_unit, 2).'/'.$item->unit.' · reorder at '.$item->qty($item->reorder_level),
                'fields' => ['name' => $item->name, 'inventory_category_id' => $item->inventory_category_id ?? '', 'reorder_level' => (float) $item->reorder_level, 'is_active' => (bool) $item->is_active],
                'levels' => $item->levels->map(fn ($l) => ['location' => $l->location?->name, 'qty' => $item->qty($l->quantity), 'negative' => (float) $l->quantity < 0]),
            ],
            'movements' => $item->movements()->with(['location', 'user'])->limit(50)->get()->map(fn ($m) => [
                'id' => $m->id,
                'at' => $m->created_at->format('M j, g:i A'),
                'type' => $m->typeLabel(),
                'notes' => $m->notes,
                'location' => $m->location?->name,
                'qty' => ((float) $m->quantity > 0 ? '+' : '').$item->qty($m->quantity),
                'out' => (float) $m->quantity < 0,
                'balance' => $item->qty($m->balance_after),
                'by' => ($m->user?->name ?? 'system').($m->reference ? ' · '.$m->reference : ''),
            ]),
            'locations' => StockLocation::query()->orderBy('name')->get(['id', 'name'])->map(fn ($l) => [$l->id, $l->name]),
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [$c->id, $c->name]),
            'tabs' => self::tabs('stock'),
            'can' => ['manage' => $request->user()->hasPermissionTo('inventory.manage')],
            'urls' => ['index' => route('inventory.index'), 'move' => route('inventory.items.move', $item->id), 'update' => route('inventory.items.update', $item->id)],
        ]);
    }

    /** receive | issue | waste | count | transfer */
    public function move(Request $request, string $item)
    {
        $this->authorizeTo($request, 'inventory.manage');
        $item = InventoryItem::query()->findOrFail($item);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['receive', 'issue', 'waste', 'count', 'transfer'])],
            'location_id' => ['required', 'integer'],
            'to_location_id' => ['nullable', 'integer', 'required_if:action,transfer'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255', 'required_if:action,waste'],
        ]);

        $location = StockLocation::query()->findOrFail($validated['location_id']);
        $quantity = (float) $validated['quantity'];
        $user = $request->user();

        match ($validated['action']) {
            'receive' => $this->inventory->receive($item, $location, $quantity, (float) ($validated['unit_cost'] ?? $item->cost_per_unit), $user, $validated['notes'] ?? null),
            'issue' => $this->inventory->issue($item, $location, $quantity, $user, $validated['notes'] ?? null),
            'waste' => $this->inventory->waste($item, $location, $quantity, $validated['notes'], $user),
            'count' => $this->inventory->count($item, $location, $quantity, $user),
            'transfer' => $this->inventory->transfer($item, $location, StockLocation::query()->findOrFail($validated['to_location_id']), $quantity, $user),
        };

        return back()->with('success', 'Stock updated.');
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeTo($request, 'inventory.manage');
        InventoryCategory::create($request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('inventory_categories')->where('tenant_id', app(TenantContext::class)->id())]]));

        return back()->with('success', 'Category added.');
    }

    public function storeLocation(Request $request)
    {
        $this->authorizeTo($request, 'inventory.manage');
        $tenantId = app(TenantContext::class)->id();

        StockLocation::create($request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('stock_locations')->where('tenant_id', $tenantId)],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('tenant_id', $tenantId)],
        ]));

        return back()->with('success', 'Location added.');
    }

    public function storeSupplier(Request $request)
    {
        $this->authorizeTo($request, 'purchasing.manage');

        Supplier::create($request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('suppliers')->where('tenant_id', app(TenantContext::class)->id())],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
        ]));

        return back()->with('success', 'Supplier added.');
    }

    /** Sub-navigation shared by the inventory screens (React). */
    public static function tabs(string $active): array
    {
        return collect([['stock', 'Stock', 'inventory.index'], ['po', 'Purchase orders', 'inventory.purchase-orders.index'], ['recipes', 'Recipes', 'inventory.recipes']])
            ->map(fn ($t) => ['label' => $t[1], 'href' => route($t[2]), 'active' => $t[0] === $active])->all();
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
