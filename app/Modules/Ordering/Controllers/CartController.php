<?php

namespace App\Modules\Ordering\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Events\CartChanged;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Marketplace cart and checkout (Phase 11). Browsing and the cart work
 * signed out; checkout requires an account. Every price shown or charged
 * comes from OrderService::quote() inside the restaurant's tenant.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
        private readonly TenantContext $context,
    ) {}

    public function add(Request $request, string $restaurant)
    {
        $listing = $this->orderable($restaurant);

        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
            'options' => ['nullable', 'array'],
            'options.*' => ['integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.OrderService::MAX_QUANTITY],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Reject bad modifier choices now rather than at checkout.
        $this->context->runAs($listing, fn () => $this->orders->quote($listing, [[
            'item_id' => $validated['item_id'],
            'option_ids' => $validated['options'] ?? [],
            'quantity' => $validated['quantity'],
        ]]));

        $replaced = $this->cart->add($listing, (int) $validated['item_id'], $validated['options'] ?? [], (int) $validated['quantity'], $validated['notes'] ?? null);

        if ($request->user()) {
            CartChanged::dispatch($request->user(), $listing, array_values($this->cart->get()['lines']));
        }

        if ($request->wantsJson()) {
            return response()->json($this->summaryData() + ['replaced' => $replaced]);
        }

        return back()->with('success', $replaced ? 'Your previous cart was replaced — carts hold one restaurant at a time.' : 'Added to your cart.');
    }

    /** Cart for the menu widget: priced lines, count and subtotal. */
    public function summary()
    {
        return response()->json($this->summaryData() + ['replaced' => false]);
    }

    public function show(Request $request)
    {
        $cart = $this->cart->get();
        $listing = $cart['restaurant_id'] ? Restaurant::publicQuery()->find($cart['restaurant_id']) : null;
        $quote = null;
        $error = null;

        if ($listing && $cart['lines'] !== []) {
            try {
                $quote = $this->context->runAs($listing, fn () => $this->orders->quote($listing, array_values($cart['lines']), $request->query('promo_code')));
            } catch (ValidationException $e) {
                $error = collect($e->errors())->flatten()->first();
                if ($request->filled('promo_code')) {
                    $quote = $this->context->runAs($listing, fn () => rescue(fn () => $this->orders->quote($listing, array_values($cart['lines'])), null, false));
                }
            }
        }

        $zones = $listing && $listing->delivery_enabled ? $this->context->runAs($listing, fn () => $listing->deliveryZones()->active()->get()) : collect();
        $stays = $listing ? $this->context->runAs($listing, fn () => $this->orders->roomServiceStays($listing, $request->user())) : collect();

        return Inertia::render('Order/Checkout', [
            'restaurant' => $listing ? [
                'name' => $listing->name,
                'url' => route('marketplace.restaurants.show', $listing->slug),
                'tax_rate' => (float) $listing->tax_rate,
                'tax_inclusive' => (bool) $listing->tax_inclusive,
                'prep_minutes' => (int) $listing->prep_minutes,
            ] : null,
            'lines' => $quote ? array_map(fn (string $key, array $line) => [
                'key' => $key,
                'name' => $line['name'],
                'unit' => $line['unit_price'],
                'mods' => collect($line['modifiers'])->pluck('name')->implode(', '),
                'notes' => $line['notes'],
                'qty' => $line['quantity'],
                'total' => $line['line_total'],
            ], array_keys($cart['lines']), $quote['lines']) : [],
            'quote' => $quote ? [
                'subtotal' => $quote['subtotal'],
                'discount' => $quote['discount'],
                'tax' => $quote['tax'],
                'total' => $quote['total'],
                'promo' => $quote['promotion'] ? ['code' => $quote['promotion']->code, 'label' => $quote['promotion']->label()] : null,
            ] : null,
            'error' => $error,
            'promoCode' => (string) $request->query('promo_code', ''),
            'onlinePayments' => PaymentService::enabled(),
            'zones' => $zones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'terms' => $z->termsLabel(), 'fee' => (float) $z->fee, 'free_over' => $z->free_over !== null ? (float) $z->free_over : null, 'radius' => $z->radius_km !== null])->values(),
            'stays' => $stays->flatMap(fn ($stay) => $stay->rooms->map(fn ($r) => [
                'value' => $stay->id.':'.$r->room_id,
                'label' => 'Room '.$r->room?->label().' · '.$stay->property->name.' ('.$stay->reference.')',
            ]))->values(),
            'minSchedule' => $listing ? now()->addMinutes((int) $listing->prep_minutes)->format('Y-m-d\TH:i') : null,
            'old' => (object) $request->old(),
            'signedIn' => (bool) $request->user(),
            'urls' => [
                'self' => route('cart.show'),
                'checkout' => route('cart.checkout'),
                'cartBase' => url('/cart'),
                'signIn' => route('continue', ['to' => '/cart']),
                'browse' => route('marketplace.restaurants.index'),
                'home' => url('/'),
            ],
        ]);
    }

    public function update(Request $request, string $key)
    {
        $this->cart->update($key, (int) $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.OrderService::MAX_QUANTITY]])['quantity']);

        return $request->wantsJson() ? response()->json($this->summaryData() + ['replaced' => false]) : back();
    }

    /** @return array{restaurant: ?array, lines: list<array>, count: int, subtotal: float, currency: string, error: ?string} */
    private function summaryData(): array
    {
        $cart = $this->cart->get();
        $listing = $cart['restaurant_id'] ? Restaurant::publicQuery()->find($cart['restaurant_id']) : null;
        $data = ['restaurant' => $listing ? ['name' => $listing->name, 'slug' => $listing->slug] : null, 'lines' => [], 'count' => 0, 'subtotal' => 0.0, 'currency' => 'PHP', 'error' => null];

        if (! $listing || $cart['lines'] === []) {
            return $data;
        }

        try {
            $quote = $this->context->runAs($listing, fn () => $this->orders->quote($listing, array_values($cart['lines'])));
        } catch (ValidationException $e) {
            return ['error' => collect($e->errors())->flatten()->first()] + $data;
        }

        $data['lines'] = array_map(fn (string $key, array $line) => [
            'key' => $key,
            'name' => $line['name'],
            'mods' => collect($line['modifiers'])->pluck('name')->implode(', '),
            'notes' => $line['notes'],
            'qty' => $line['quantity'],
            'total' => $line['line_total'],
        ], array_keys($cart['lines']), $quote['lines']);
        $data['count'] = array_sum(array_column($quote['lines'], 'quantity'));
        $data['subtotal'] = $quote['subtotal'];

        return $data;
    }

    public function checkout(Request $request)
    {
        $cart = $this->cart->get();
        abort_if(! $cart['restaurant_id'] || $cart['lines'] === [], 404);

        $listing = $this->orderable(Restaurant::publicQuery()->findOrFail($cart['restaurant_id'])->slug);

        $validated = $request->validate([
            'fulfillment' => ['required', Rule::in([Order::PICKUP, Order::DELIVERY, Order::ROOM_SERVICE])],
            'payment_method' => ['required', Rule::in([Order::PAY_ONLINE, Order::PAY_CASH, Order::PAY_ROOM])],
            'room_stay' => ['nullable', 'regex:/^\d+:\d+$/'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_zone_id' => ['nullable', 'integer'],
            'delivery_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'scheduled_for' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();

        // "bookingId:roomId" from the room service picker.
        [$validated['booking_id'], $validated['room_id']] = array_map('intval', explode(':', $validated['room_stay'] ?? '0:0'));

        $order = $this->context->runAs($listing, fn () => $this->orders->place(
            $listing,
            array_values($cart['lines']),
            $validated + ['customer_name' => $user->name],
            $user,
        ));

        $this->cart->clear();
        CartChanged::dispatch($user, $listing, []);

        if ($order->needsPayment()) {
            $payment = $this->context->runAs($order, fn () => $this->payments->checkoutOrder($order, $user));

            return redirect()->away($payment->checkout_url);
        }

        return redirect()->route('account.orders.show', $order->reference)
            ->with('success', 'Order '.$order->reference.' placed — pay '.($order->fulfillment === Order::DELIVERY ? 'on delivery' : 'at pickup').'.');
    }

    private function orderable(string $slug): Restaurant
    {
        $listing = Restaurant::publicQuery()->where('slug', $slug)->firstOrFail();

        abort_unless($this->orders->acceptsOrders($listing), 404);

        return $listing;
    }
}
