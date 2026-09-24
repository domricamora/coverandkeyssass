<?php

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Payments\Models\Payment;
use App\Support\AuditLogger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Payment lifecycle (Phase 07):
 *
 *   booking (pending) → checkout() → PayMongo checkout → guest pays
 *   → webhook / return URL → sync(): server-side lookup of the checkout
 *   session → markPaid() → booking confirmed.
 *
 * Rules: a booking is confirmed only after PayMongo's API itself reports
 * the payment paid for the exact amount; markPaid() is idempotent under a
 * row lock, so duplicate webhooks, a webhook racing the return URL, or a
 * replayed event can never double-record a payment or re-confirm.
 */
class PaymentService
{
    public function __construct(
        private readonly PayMongoGateway $gateway,
        private readonly BookingService $bookings,
        private readonly AuditLogger $audit,
    ) {}

    public static function enabled(): bool
    {
        return filled(config('services.paymongo.secret_key'));
    }

    /** Start (or resume) a PayMongo checkout. Runs inside the booking's tenant. */
    public function checkout(Booking $booking, User $user): Payment
    {
        if (! in_array($booking->status, [Booking::PENDING, Booking::HELD], true)) {
            $this->fail('This booking does not need a payment.');
        }

        if (Payment::query()->where('booking_id', $booking->id)->where('status', Payment::PAID)->exists()) {
            $this->fail('This booking is already paid.');
        }

        // Reuse an open checkout so a double click never opens two sessions.
        $open = Payment::query()->where('booking_id', $booking->id)->where('status', Payment::PENDING)->whereNotNull('checkout_url')->latest('id')->first();

        if ($open) {
            return $open;
        }

        $amount = (int) round((float) $booking->total * 100);
        $booking->loadMissing('property');

        try {
            $session = $this->gateway->createCheckoutSession([
                'line_items' => [[
                    'name' => Str::limit('Stay at '.$booking->property->name, 250),
                    'description' => $booking->check_in->format('M j').' – '.$booking->check_out->format('M j, Y').' · '.$booking->nights().' night(s)',
                    'amount' => $amount,
                    'currency' => $booking->currency,
                    'quantity' => 1,
                ]],
                'payment_method_types' => config('services.paymongo.methods'),
                'description' => 'Booking '.$booking->reference,
                'reference_number' => $booking->reference,
                'success_url' => route('account.payments.return', $booking->reference),
                'cancel_url' => route('account.bookings.show', $booking->reference),
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'metadata' => ['booking_reference' => $booking->reference],
            ]);
        } catch (RequestException $e) {
            report($e);
            $this->fail('The payment provider is unavailable right now. Please try again in a moment.');
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'checkout_session_id' => $session['id'],
            'payment_intent_id' => data_get($session, 'attributes.payment_intent.id'),
            'checkout_url' => data_get($session, 'attributes.checkout_url'),
            'amount' => $amount / 100,
            'currency' => $booking->currency,
            'status' => Payment::PENDING,
        ]);

        $this->audit->log('payment.checkout_started', $payment, null, ['booking' => $booking->reference, 'amount' => $payment->amount]);

        return $payment;
    }

    /**
     * Ask PayMongo for the checkout session and record the payment when it
     * is paid for the expected amount. Never trusts caller-supplied data.
     */
    public function sync(Payment $payment): Payment
    {
        if ($payment->status !== Payment::PENDING || ! $payment->checkout_session_id) {
            return $payment;
        }

        $session = $this->gateway->retrieveCheckoutSession($payment->checkout_session_id);

        $paid = collect(data_get($session, 'attributes.payments', []))
            ->first(fn ($p) => data_get($p, 'attributes.status') === 'paid');

        if (! $paid) {
            return $payment;
        }

        if ((int) data_get($paid, 'attributes.amount') !== $payment->amountInCentavos()
            || strtoupper((string) data_get($paid, 'attributes.currency', $payment->currency)) !== strtoupper($payment->currency)) {
            $this->audit->log('payment.amount_mismatch', $payment, null, [
                'expected' => $payment->amountInCentavos(),
                'received' => data_get($paid, 'attributes.amount'),
            ], $payment->tenant_id);

            return $payment;
        }

        return $this->markPaid($payment, (string) data_get($paid, 'id'), data_get($session, 'attributes.payment_method_used') ?? data_get($paid, 'attributes.source.type'));
    }

    /** Record a declined payment. The booking stays pending so the guest can retry. */
    public function markFailed(Payment $payment, ?string $reason): void
    {
        if ($payment->status !== Payment::PENDING) {
            return;
        }

        $payment->forceFill(['status' => Payment::FAILED, 'failure_reason' => Str::limit($reason ?: 'Payment failed', 250)])->save();

        $this->audit->log('payment.failed', $payment, null, ['reason' => $payment->failure_reason], $payment->tenant_id);
    }

    /**
     * Refund the booking's paid payment through PayMongo (listener on
     * BookingTransitioning → refunded). Throwing vetoes the transition, so
     * a booking is never marked refunded while the money was not returned.
     * Bookings paid offline have no payment row and refund offline.
     */
    public function refundBooking(Booking $booking): void
    {
        $payment = Payment::query()->where('booking_id', $booking->id)->where('status', Payment::PAID)->first();

        if (! $payment) {
            return;
        }

        try {
            $refund = $this->gateway->createRefund($payment->provider_payment_id, $payment->amountInCentavos(), 'requested_by_customer', 'Booking '.$booking->reference);
        } catch (RequestException $e) {
            report($e);
            $this->fail('PayMongo did not accept the refund: '.(data_get($e->response->json(), 'errors.0.detail') ?? 'provider error').'.');
        }

        $payment->forceFill([
            'status' => Payment::REFUNDED,
            'refund_id' => data_get($refund, 'id'),
            'refunded_amount' => $payment->amount,
            'refunded_at' => now(),
        ])->save();

        $this->audit->log('payment.refunded', $payment, null, ['refund_id' => $payment->refund_id, 'amount' => $payment->amount]);
    }

    private function markPaid(Payment $payment, string $providerPaymentId, ?string $method): Payment
    {
        return $this->bookings->asTenantOf($payment, function () use ($payment, $providerPaymentId, $method) {
            $recorded = DB::transaction(function () use ($payment, $providerPaymentId, $method): bool {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                if ($locked->status === Payment::PAID) {
                    return false; // already recorded by a concurrent webhook / return
                }

                $locked->forceFill([
                    'status' => Payment::PAID,
                    'provider_payment_id' => $providerPaymentId,
                    'method' => $method,
                    'paid_at' => now(),
                    'failure_reason' => null,
                ])->save();

                return true;
            });

            $payment->refresh();

            if (! $recorded) {
                return $payment;
            }

            $this->audit->log('payment.paid', $payment, null, ['amount' => $payment->amount, 'method' => $method]);

            $booking = $payment->booking;

            if ($booking->canTransitionTo(Booking::CONFIRMED)) {
                $this->bookings->transition($booking, Booking::CONFIRMED);
            } else {
                // e.g. cancelled while the guest was paying — host must refund.
                $this->audit->log('payment.paid_on_inactive_booking', $payment, null, ['booking_status' => $booking->status]);
            }

            return $payment;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
