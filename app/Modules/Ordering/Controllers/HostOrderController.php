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
use Inertia\Inertia;

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
        $r = $restaurant;
        $user = $request->user();
        $manage = $user->hasPermissionTo('orders.manage');

        return Inertia::render('Restaurants/Orders', [
            'restaurant' => ['name' => $r->name, 'ordering' => (bool) $r->ordering_enabled],
            'tabs' => $this->tabs($r, 'orders'),
            'stages' => [Order::PENDING => 'New', Order::ACCEPTED => 'Accepted', Order::PREPARING => 'Preparing', Order::READY => 'Ready', Order::OUT_FOR_DELIVERY => 'Out for delivery', Order::DELIVERED => 'Delivered'],
            'open' => (clone $base)->whereIn('status', Order::OPEN)->with('driver')->orderByRaw('COALESCE(scheduled_for, created_at)')->get()
                ->map(fn (Order $o) => $this->card($r, $o, $manage)),
            'recent' => (clone $base)->whereNotIn('status', Order::OPEN)->latest()->limit(20)->get()->map(fn (Order $o) => [
                'reference' => $o->reference,
                'at' => $o->created_at->format('M j, g:i A'),
                'customer' => $o->customer_name,
                'status' => $o->status,
                'total' => $o->money($o->total),
                'href' => route('restaurants.orders.show', [$r, $o->reference]),
            ]),
            'drivers' => Driver::query()->active()->get(['id', 'name'])->map(fn ($d) => [$d->id, $d->name]),
            'promotions' => Promotion::query()->where('applies_to', Promotion::FOR_ORDERS)
                ->where(fn ($q) => $q->whereNull('restaurant_id')->orWhere('restaurant_id', $r->id))
                ->latest()->get()->map(fn (Promotion $p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'terms' => $p->label().($p->min_subtotal ? ' · min '.number_format((float) $p->min_subtotal, 0) : '').' · used '.$p->used_count.($p->max_uses ? '/'.$p->max_uses : ''),
                    'active' => (bool) $p->is_active,
                    'toggle' => $p->restaurant_id ? route('restaurants.orders.promotions.toggle', [$r, $p->id]) : null,
                ]),
            'can' => ['manage' => $manage, 'promotions' => $user->hasPermissionTo('promotions.manage')],
            'urls' => ['addPromotion' => route('restaurants.orders.promotions.store', $r), 'profile' => route('restaurants.edit', $r)],
        ]);
    }

    public function show(Request $request, string $restaurant, string $order)
    {
        $this->authorizeTo($request, 'orders.view');
        $restaurant = $this->resolveRestaurant($restaurant);

        $order = Order::query()->where('restaurant_id', $restaurant->id)->where('reference', $order)->with(['items', 'zone', 'driver'])->firstOrFail();

        $o = $order;

        return Inertia::render('Restaurants/Order', [
            'order' => $this->card($restaurant, $o, $request->user()->hasPermissionTo('orders.manage')) + [
                'summary' => $o->created_at->format('M j, Y · g:i A').' · '.$o->fulfillmentLabel().' · '.$o->paymentLabel(),
                'phone' => $o->customer_phone,
                'address' => $o->delivery_address,
                'zone' => $o->zone?->name,
                'map' => $o->delivery_lat ? 'https://maps.google.com/?q='.$o->delivery_lat.','.$o->delivery_lng : null,
                'driver_phone' => $o->driver?->phone,
                'notes' => $o->notes,
                'cancelled' => $o->cancellation_reason,
                'lines' => $o->items->map(fn ($i) => ['id' => $i->id, 'qty' => $i->quantity, 'name' => $i->name, 'mods' => $i->modifiers ? $i->modifierLabel() : null, 'notes' => $i->notes, 'total' => $o->money($i->line_total)]),
                'totals' => array_values(array_filter([
                    ['Subtotal', $o->money($o->subtotal)],
                    (float) $o->discount_total > 0 ? ['Discount', '−'.$o->money($o->discount_total)] : null,
                    ['Tax ('.(float) $o->tax_rate.'% '.($o->tax_inclusive ? 'included' : 'added').')', $o->money($o->tax_total)],
                    (float) $o->delivery_fee > 0 ? ['Delivery fee', $o->money($o->delivery_fee)] : null,
                ])),
            ],
            'drivers' => Driver::query()->active()->get(['id', 'name'])->map(fn ($d) => [$d->id, $d->name]),
            'tabs' => $this->tabs($restaurant, 'orders'),
            'urls' => ['index' => route('restaurants.orders.index', $restaurant)],
        ]);
    }

    /** One order as the queue / detail screens show it. */
    private function card($restaurant, Order $o, bool $manage): array
    {
        $done = in_array($o->status, [Order::PENDING, Order::DELIVERED, Order::COMPLETED, Order::CANCELLED, Order::REFUNDED], true);

        return [
            'reference' => $o->reference,
            'status' => $o->status,
            'customer' => $o->customer_name,
            'items' => (int) $o->items_sum_quantity,
            'total' => $o->money($o->total),
            'meta' => $o->fulfillmentLabel().' · '.$o->paymentLabel().' · '.$o->created_at->diffForHumans(),
            'scheduled' => $o->scheduled_for?->format('D g:i A'),
            'eta' => $o->estimated_at?->format('g:i A'),
            'delivery' => $o->fulfillment === Order::DELIVERY,
            'driver_id' => $o->driver_id,
            'driver' => $o->driver?->name,
            'can_assign' => $manage && $o->fulfillment === Order::DELIVERY && ! $done,
            'next' => $manage ? array_values($o->nextStates()) : [],
            'href' => route('restaurants.orders.show', [$restaurant, $o->reference]),
            'transition' => route('restaurants.orders.transition', [$restaurant, $o->reference]),
            'assign' => route('restaurants.orders.driver', [$restaurant, $o->reference]),
        ];
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
