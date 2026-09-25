<?php

namespace App\Modules\Ordering\Controllers;

use App\Modules\Booking\Models\Promotion;
use App\Modules\Delivery\Models\Driver;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Controllers\RestaurantManagementController;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Host order queue (Phase 11): open orders by stage, recent history, order
 * detail with state actions, and order promo codes for the restaurant.
 */
class HostOrderController extends RestaurantManagementController
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'orders.view');
        $restaurant = $this->resolveRestaurant($restaurant);

        $base = Order::query()->where('restaurant_id', $restaurant->id)->withSum('items', 'quantity');

        return view('ordering::host.index', [
            'restaurant' => $restaurant,
            'open' => (clone $base)->whereIn('status', Order::OPEN)->with('driver')->orderByRaw('COALESCE(scheduled_for, created_at)')->get()->groupBy('status'),
            'drivers' => Driver::query()->active()->get(),
            'recent' => (clone $base)->whereNotIn('status', Order::OPEN)->latest()->limit(20)->get(),
            'promotions' => Promotion::query()->where('applies_to', Promotion::FOR_ORDERS)
                ->where(fn ($q) => $q->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurant->id))
                ->latest()->get(),
            'title' => 'Orders — '.$restaurant->name,
        ]);
    }

    public function show(Request $request, string $restaurant, string $order)
    {
        $this->authorizeTo($request, 'orders.view');
        $restaurant = $this->resolveRestaurant($restaurant);

        $order = Order::query()->where('restaurant_id', $restaurant->id)->where('reference', $order)->with(['items', 'zone', 'driver'])->firstOrFail();

        return view('ordering::host.show', [
            'restaurant' => $restaurant,
            'order' => $order,
            'drivers' => Driver::query()->active()->get(),
            'title' => 'Order '.$order->reference,
        ]);
    }

    public function transition(Request $request, string $restaurant, string $order)
    {
        $this->authorizeTo($request, 'orders.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $order = Order::query()->where('restaurant_id', $restaurant->id)->where('reference', $order)->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Register tickets settle and refund through the POS drawer, never from the queue.
        if ($order->channel === Order::CHANNEL_POS && in_array($validated['status'], [Order::REFUNDED, Order::COMPLETED], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Close or refund register tickets at the POS.']);
        }

        $this->orders->transition($order, $validated['status'], $validated['reason'] ?? null);

        return back()->with('success', 'Order '.$order->reference.' is now '.strtolower($order->statusLabel()).'.');
    }

    public function assignDriver(Request $request, string $restaurant, string $order)
    {
        $this->authorizeTo($request, 'orders.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $order = Order::query()->where('restaurant_id', $restaurant->id)->where('reference', $order)->firstOrFail();
        $driverId = $request->validate(['driver_id' => ['nullable', 'integer']])['driver_id'] ?? null;

        $this->orders->assignDriver($order, $driverId ? Driver::query()->findOrFail($driverId) : null);

        return back()->with('success', $order->driver_id ? 'Driver assigned to '.$order->reference.'.' : 'Driver cleared.');
    }

    public function storePromotion(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'promotions.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('promotions')->where('tenant_id', app(TenantContext::class)->id())],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in([Promotion::TYPE_PERCENT, Promotion::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'min:0.01', $request->input('type') === Promotion::TYPE_PERCENT ? 'max:100' : 'max:9999999'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        Promotion::create($validated + [
            'applies_to' => Promotion::FOR_ORDERS,
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        return back()->with('success', 'Order promo code '.$validated['code'].' created.');
    }

    public function togglePromotion(Request $request, string $restaurant, string $promotion)
    {
        $this->authorizeTo($request, 'promotions.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $promotion = Promotion::query()->where('applies_to', Promotion::FOR_ORDERS)->where('restaurant_id', $restaurant->id)->findOrFail($promotion);
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('success', 'Promo code '.$promotion->code.' '.($promotion->is_active ? 'reactivated' : 'deactivated').'.');
    }
}
