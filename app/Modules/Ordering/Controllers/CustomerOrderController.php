<?php

namespace App\Modules\Ordering\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer portal "Orders" tab (Phase 11): history, detail, cancel while
 * pending, and PayMongo checkout / return for online orders. Orders are
 * looked up through Order::forCustomer(), so another customer's reference
 * is a plain 404.
 */
class CustomerOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request)
    {
        return \Inertia\Inertia::render('Account/Orders', [
            'orders' => Order::forCustomer($request->user())->with('restaurant')->latest()->paginate(15)->through(fn (Order $o) => [
                'reference' => $o->reference,
                'restaurant' => $o->restaurant?->name ?? 'Restaurant',
                'when' => $o->created_at->format('M j, Y · g:i A'),
                'how' => $o->fulfillmentLabel(),
                'status' => $o->status,
                'statusLabel' => $o->statusLabel(),
                'total' => $o->money($o->total),
                'href' => route('account.orders.show', $o->reference),
            ]),
            'tabs' => \App\Modules\Customer\Controllers\AccountController::nav('account.orders.index'),
            'urls' => ['browse' => route('marketplace.restaurants.index')],
        ]);
    }

    public function show(Request $request, string $order)
    {
        $order = $this->find($request, $order);
        $items = $this->context->runAs($order, fn () => $order->load('driver')->items()->get());
        $open = in_array($order->status, Order::OPEN, true);

        return \Inertia\Inertia::render('Account/Order', [
            'order' => [
                'reference' => $order->reference,
                'restaurant' => $order->restaurant?->name ?? 'Restaurant',
                'meta' => $order->created_at->format('M j, Y · g:i A').' · '.$order->fulfillmentLabel().' · '.$order->paymentLabel(),
                'status' => $order->status,
                'statusLabel' => $order->statusLabel(),
                'payment' => ucfirst((string) $order->payment_status),
                'details' => array_values(array_filter([
                    $order->delivery_address ? 'Deliver to: '.$order->delivery_address : null,
                    $order->scheduled_for ? 'Scheduled for '.$order->scheduled_for->format('D, M j · g:i A') : null,
                    $order->status === 'out_for_delivery' && $order->driver ? 'On the way with '.$order->driver->name.($order->driver->phone ? ' · '.$order->driver->phone : '') : null,
                    $order->delivered_at ? 'Delivered '.$order->delivered_at->format('g:i A') : null,
                ])),
                'eta' => $order->estimated_at && $open ? 'Estimated '.($order->fulfillment === 'delivery' ? 'arrival' : 'ready').': '.$order->estimated_at->format('g:i A') : null,
                'lines' => $items->map(fn ($i) => ['id' => $i->id, 'qty' => $i->quantity, 'name' => $i->name, 'mods' => $i->modifiers ? $i->modifierLabel() : null, 'notes' => $i->notes, 'total' => $order->money($i->line_total), 'menuItemId' => $i->menu_item_id]),
                'totals' => array_values(array_filter([
                    ['Subtotal', $order->money($order->subtotal)],
                    (float) $order->discount_total > 0 ? ['Discount', '−'.$order->money($order->discount_total)] : null,
                    ['Tax ('.(float) $order->tax_rate.'% '.($order->tax_inclusive ? 'included' : 'added').')', $order->money($order->tax_total)],
                    (float) $order->delivery_fee > 0 ? ['Delivery fee', $order->money($order->delivery_fee)] : null,
                ])),
                'total' => $order->money($order->total),
            ],
            'providers' => $order->needsPayment() ? PaymentService::providers() : [],
            'can' => [
                'cancel' => $order->status === Order::PENDING,
                'review' => $order->status === 'completed' && ! \App\Modules\Marketplace\Models\Review::query()->withTrashed()->where('order_id', $order->id)->exists(),
            ],
            'tabs' => \App\Modules\Customer\Controllers\AccountController::nav('account.orders.index'),
            'urls' => [
                'orders' => route('account.orders.index'),
                'pay' => route('account.orders.pay', $order->reference),
                'cancel' => route('account.orders.cancel', $order->reference),
                'review' => route('account.orders.review', $order->reference),
                'message' => route('account.messages.create', ['order' => $order->reference]),
            ],
        ]);
    }

    public function cancel(Request $request, string $order)
    {
        $order = $this->find($request, $order);

        if ($order->status !== Order::PENDING) {
            throw ValidationException::withMessages(['order' => 'The restaurant already started on this order — please call them to cancel.']);
        }

        $this->context->runAs($order, fn () => $this->orders->transition($order, Order::CANCELLED, 'Cancelled by customer'));

        return back()->with('success', 'Order '.$order->reference.' cancelled.'.($order->payment_status === Order::PAID ? ' The restaurant will refund your payment.' : ''));
    }

    /** Review a completed order: restaurant + category ratings + per-dish stars (Phase 24). */
    public function review(Request $request, string $order)
    {
        $order = $this->find($request, $order);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_food' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_service' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_value' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:3000'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $this->context->runAs($order, fn () => app(\App\Modules\Reviews\Services\ReviewService::class)->reviewOrder($order, $request->user(), $validated, array_filter($validated['items'] ?? [])));

        return back()->with('success', 'Thanks for your review!');
    }

    public function pay(Request $request, string $order)
    {
        abort_unless(PaymentService::enabled(), 404);

        $order = $this->find($request, $order);

        $payment = $this->context->runAs($order, fn () => $this->payments->checkoutOrder($order, $request->user(), $request->input('provider')));

        return redirect()->away($payment->checkout_url);
    }

    /** PayMongo success_url: triggers a server-side lookup, proves nothing by itself. */
    public function paymentReturn(Request $request, string $order)
    {
        $order = $this->find($request, $order);

        foreach (Payment::forCustomer($request->user())->where('order_id', $order->id)->where('status', Payment::PENDING)->get() as $payment) {
            try {
                $this->payments->sync($payment);
            } catch (RequestException $e) {
                report($e); // the webhook will still arrive
            }
        }

        $paid = $order->refresh()->payment_status === Order::PAID;

        return redirect()->route('account.orders.show', $order->reference)->with(
            $paid ? 'success' : 'warning',
            $paid ? 'Payment received — the restaurant has your order.' : 'We are waiting for PayMongo to confirm your payment.',
        );
    }

    private function find(Request $request, string $reference): Order
    {
        return Order::forCustomer($request->user())->where('reference', $reference)->firstOrFail();
    }
}
