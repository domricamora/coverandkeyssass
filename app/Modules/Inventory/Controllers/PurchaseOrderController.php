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

        return view('inventory::purchase-orders.index', [
            'orders' => PurchaseOrder::query()->with(['supplier', 'location'])->latest()->paginate(20),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'locations' => StockLocation::query()->orderBy('name')->get(),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(),
            'title' => 'Purchase orders',
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

        return view('inventory::purchase-orders.show', [
            'po' => $this->find($po)->load(['supplier', 'location', 'lines.item']),
            'title' => 'Purchase order '.$po,
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
