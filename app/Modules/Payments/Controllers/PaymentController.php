<?php

namespace App\Modules\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

/**
 * Guest side of payments: start checkout, the PayMongo return URL, and the
 * transaction history tab of the customer portal.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly BookingService $bookings,
    ) {}

    public function pay(Request $request, string $booking)
    {
        abort_unless(PaymentService::enabled(), 404);

        $booking = $this->findBooking($request, $booking);

        $payment = $this->bookings->asTenantOf($booking, fn () => $this->payments->checkout($booking, $request->user(), $request->input('provider')));

        return redirect()->away($payment->checkout_url);
    }

    /**
     * success_url of the checkout. The redirect itself proves nothing — it
     * only triggers a server-side lookup of the session at PayMongo.
     */
    public function return(Request $request, string $booking)
    {
        $booking = $this->findBooking($request, $booking);

        $pending = Payment::forCustomer($request->user())
            ->where('booking_id', $booking->id)
            ->where('status', Payment::PENDING)
            ->get();

        foreach ($pending as $payment) {
            try {
                $this->payments->sync($payment);
            } catch (RequestException $e) {
                report($e); // the webhook will still arrive
            }
        }

        $paid = Payment::forCustomer($request->user())->where('booking_id', $booking->id)->where('status', Payment::PAID)->exists();

        return redirect()->route('account.bookings.show', $booking->reference)->with(
            $paid ? 'success' : 'warning',
            $paid ? 'Payment received — your booking is confirmed.' : 'We are waiting for PayMongo to confirm your payment. This page updates once it does.',
        );
    }

    public function index(Request $request)
    {
        return \Inertia\Inertia::render('Account/Payments', [
            'tabs' => \App\Modules\Customer\Controllers\AccountController::nav('account.payments.index'),
            'payments' => Payment::forCustomer($request->user())
                ->with(['booking' => fn ($q) => $q->withoutGlobalScope('tenant'), 'order' => fn ($q) => $q->withoutGlobalScope('tenant')])
                ->latest()
                ->paginate(15)
                ->through(fn (Payment $p) => [
                    'id' => $p->id,
                    'date' => ($p->paid_at ?? $p->created_at)->format('M j, Y'),
                    'for' => $p->booking ? 'Stay '.$p->booking->reference : ($p->order ? 'Order '.$p->order->reference : 'Payment'),
                    'href' => $p->booking ? route('account.bookings.show', $p->booking->reference) : ($p->order ? route('account.orders.show', $p->order->reference) : null),
                    'method' => \Illuminate\Support\Str::headline($p->method ?: $p->provider),
                    'status' => $p->status,
                    'amount' => \App\Support\Currency::format($p->amount),
                ]),
        ]);
    }

    private function findBooking(Request $request, string $reference): Booking
    {
        return Booking::forCustomer($request->user())->where('reference', $reference)->firstOrFail();
    }
}
