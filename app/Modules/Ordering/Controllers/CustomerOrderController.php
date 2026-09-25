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
        return view('ordering::account.index', [
            'orders' => Order::forCustomer($request->user())->with('restaurant')->latest()->paginate(15),
        ]);
    }

    public function show(Request $request, string $order)
    {
        $order = $this->find($request, $order);

        return view('ordering::account.show', [
            'order' => $order,
            'items' => $this->context->runAs($order, fn () => $order->load('driver')->items()->get()),
            'onlinePayments' => PaymentService::enabled(),
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

    public function pay(Request $request, string $order)
    {
        abort_unless(PaymentService::enabled(), 404);

        $order = $this->find($request, $order);

        $payment = $this->context->runAs($order, fn () => $this->payments->checkoutOrder($order, $request->user()));

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
