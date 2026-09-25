<?php

namespace App\Modules\PlatformAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin (Phase 28): every business's bookings, orders and payments,
 * searchable, plus refunds. A refund always goes through the booking /
 * order state machine (cancel if needed → refunded), so PayMongo, wallet,
 * commissions, folio and accounting all follow as they do for a host.
 */
class ActivityController extends Controller
{
    private const NO_SCOPE = ['tenant'];

    public function bookings(Request $request)
    {
        $bookings = Booking::query()->withoutGlobalScopes(self::NO_SCOPE)
            ->with(['property' => fn ($q) => $q->withoutGlobalScopes(self::NO_SCOPE)->withTrashed(), 'tenant'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhere('guest_name', 'like', "%{$term}%")->orWhere('guest_email', 'like', "%{$term}%")))
            ->latest('id')->paginate(30)->withQueryString();

        return view('platform-admin::bookings', ['bookings' => $bookings, 'statuses' => Booking::statuses()]);
    }

    public function orders(Request $request)
    {
        $orders = Order::query()->withoutGlobalScopes(self::NO_SCOPE)
            ->with(['restaurant' => fn ($q) => $q->withoutGlobalScopes(self::NO_SCOPE)->withTrashed()])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhere('customer_name', 'like', "%{$term}%")))
            ->latest('id')->paginate(30)->withQueryString();

        return view('platform-admin::orders', ['orders' => $orders]);
    }

    public function payments(Request $request)
    {
        $payments = Payment::query()->withoutGlobalScopes(self::NO_SCOPE)
            ->with(['booking' => fn ($q) => $q->withoutGlobalScopes(self::NO_SCOPE), 'order' => fn ($q) => $q->withoutGlobalScopes(self::NO_SCOPE), 'user:id,name,email'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(30)->withQueryString();

        return view('platform-admin::payments', ['payments' => $payments]);
    }

    public function refund(Request $request, string $payment, AuditLogger $audit)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $payment = Payment::query()->withoutGlobalScopes(self::NO_SCOPE)->findOrFail($payment);

        if ($payment->status !== Payment::PAID) {
            throw ValidationException::withMessages(['refund' => 'Only paid payments can be refunded.']);
        }

        app(TenantContext::class)->runAs($payment, function () use ($payment, $data) {
            if ($payment->booking_id) {
                $booking = $payment->booking()->firstOrFail();
                $service = app(BookingService::class);
                if ($booking->canTransitionTo(Booking::CANCELLED)) {
                    $service->transition($booking, Booking::CANCELLED, 'Platform: '.$data['reason']);
                }
                $this->refundable($booking->canTransitionTo(Booking::REFUNDED), 'booking '.$booking->reference, $booking->status);
                $service->transition($booking, Booking::REFUNDED, 'Platform: '.$data['reason']);
            } else {
                $order = $payment->order()->firstOrFail();
                $service = app(OrderService::class);
                if ($order->canTransitionTo(Order::CANCELLED)) {
                    $service->transition($order, Order::CANCELLED, 'Platform: '.$data['reason']);
                    $order->refresh();
                }
                $this->refundable($order->canTransitionTo(Order::REFUNDED), 'order '.$order->reference, $order->status);
                $service->transition($order, Order::REFUNDED, 'Platform: '.$data['reason']);
            }
        });

        $audit->log('platform.payment.refunded', $payment, null, ['reason' => $data['reason']], $payment->tenant_id);

        return back()->with('success', 'Refund sent to PayMongo.');
    }

    private function refundable(bool $can, string $what, string $status): void
    {
        if (! $can) {
            throw ValidationException::withMessages(['refund' => 'The '.$what.' is '.str_replace('_', ' ', $status).' and cannot be refunded from here.']);
        }
    }
}
