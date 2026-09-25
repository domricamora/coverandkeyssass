<?php

namespace App\Modules\Ordering\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        return back()->with('success', $replaced ? 'Your previous cart was replaced — carts hold one restaurant at a time.' : 'Added to your cart.');
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

        return view('ordering::cart', [
            'cart' => $cart,
            'keys' => array_keys($cart['lines']),
            'listing' => $listing,
            'quote' => $quote,
            'error' => $error,
            'onlinePayments' => PaymentService::enabled(),
        ]);
    }

    public function update(Request $request, string $key)
    {
        $this->cart->update($key, (int) $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.OrderService::MAX_QUANTITY]])['quantity']);

        return back();
    }

    public function checkout(Request $request)
    {
        $cart = $this->cart->get();
        abort_if(! $cart['restaurant_id'] || $cart['lines'] === [], 404);

        $listing = $this->orderable(Restaurant::publicQuery()->findOrFail($cart['restaurant_id'])->slug);

        $validated = $request->validate([
            'fulfillment' => ['required', Rule::in([Order::PICKUP, Order::DELIVERY])],
            'payment_method' => ['required', Rule::in([Order::PAY_ONLINE, Order::PAY_CASH])],
            'customer_phone' => ['required', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();

        $order = $this->context->runAs($listing, fn () => $this->orders->place(
            $listing,
            array_values($cart['lines']),
            $validated + ['customer_name' => $user->name],
            $user,
        ));

        $this->cart->clear();

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
