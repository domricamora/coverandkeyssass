<?php

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Events\PaymentPaid;
use App\Modules\Payments\Events\PaymentRefunded;
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
        private readonly OrderService $orders,
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

        $booking->loadMissing('property');

        return $this->openCheckout(['booking_id' => $booking->id], $user, (float) $booking->total, $booking->currency, $booking->reference, [
            'name' => 'Stay at '.$booking->property->name,
            'description' => $booking->check_in->format('M j').' – '.$booking->check_out->format('M j, Y').' · '.$booking->nights().' night(s)',
            'label' => 'Booking',
            'success_url' => route('account.payments.return', $booking->reference),
            'cancel_url' => route('account.bookings.show', $booking->reference),
        ]);
    }

    /** Start (or resume) a PayMongo checkout for a food order (Phase 11). Runs inside the order's tenant. */
    public function checkoutOrder(Order $order, User $user): Payment
    {
        if (! $order->needsPayment()) {
            $this->fail('This order does not need an online payment.');
        }

        return $this->openCheckout(['order_id' => $order->id], $user, (float) $order->total, $order->currency, $order->reference, [
            'name' => 'Order from '.$order->restaurant->name,
            'description' => $order->items()->sum('quantity').' item(s) · '.$order->fulfillmentLabel(),
            'label' => 'Order',
            'success_url' => route('account.orders.payment-return', $order->reference),
            'cancel_url' => route('account.orders.show', $order->reference),
        ]);
    }

    /**
     * @param  array{booking_id: int}|array{order_id: int}  $payable
     * @param  array{name: string, description: string, label: string, success_url: string, cancel_url: string}  $display
     */
    private function openCheckout(array $payable, User $user, float $total, string $currency, string $reference, array $display): Payment
    {
        [$column, $id] = [array_key_first($payable), reset($payable)];

        if (Payment::query()->where($column, $id)->where('status', Payment::PAID)->exists()) {
            $this->fail('This '.strtolower($display['label']).' is already paid.');
        }

        // Reuse an open checkout so a double click never opens two sessions.
        $open = Payment::query()->where($column, $id)->where('status', Payment::PENDING)->whereNotNull('checkout_url')->latest('id')->first();

        if ($open) {
            return $open;
        }

        $amount = (int) round($total * 100);

        try {
            $session = $this->gateway->createCheckoutSession([
                'line_items' => [[
                    'name' => Str::limit($display['name'], 250),
                    'description' => $display['description'],
                    'amount' => $amount,
                    'currency' => $currency,
                    'quantity' => 1,
                ]],
                'payment_method_types' => config('services.paymongo.methods'),
                'description' => $display['label'].' '.$reference,
                'reference_number' => $reference,
                'success_url' => $display['success_url'],
                'cancel_url' => $display['cancel_url'],
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'metadata' => [strtolower($display['label']).'_reference' => $reference],
            ]);
        } catch (RequestException $e) {
            report($e);
            $this->fail('The payment provider is unavailable right now. Please try again in a moment.');
        }

        $payment = Payment::create($payable + [
            'user_id' => $user->id,
            'checkout_session_id' => $session['id'],
            'payment_intent_id' => data_get($session, 'attributes.payment_intent.id'),
            'checkout_url' => data_get($session, 'attributes.checkout_url'),
            'amount' => $amount / 100,
            'currency' => $currency,
            'status' => Payment::PENDING,
        ]);

        $this->audit->log('payment.checkout_started', $payment, null, [strtolower($display['label']) => $reference, 'amount' => $payment->amount]);

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
        $this->refundPaid(Payment::query()->where('booking_id', $booking->id)->where('status', Payment::PAID)->first(), 'Booking '.$booking->reference);
    }

    /** Same as refundBooking(), for a food order moving to `refunded`. */
    public function refundOrder(Order $order): void
    {
        $this->refundPaid(Payment::query()->where('order_id', $order->id)->where('status', Payment::PAID)->first(), 'Order '.$order->reference);
    }

    private function refundPaid(?Payment $payment, string $label): void
    {
        if (! $payment) {
            return;
        }

        try {
            $refund = $this->gateway->createRefund($payment->provider_payment_id, $payment->amountInCentavos(), 'requested_by_customer', $label);
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

        PaymentRefunded::dispatch($payment);
    }

    private function markPaid(Payment $payment, string $providerPaymentId, ?string $method): Payment
    {
        return $this->bookings->asTenantOf($payment, function () use ($payment, $providerPaymentId, $method) {
            DB::transaction(function () use ($payment, $providerPaymentId, $method): bool {
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

                // Same transaction: if the wallet or the confirmation fails,
                // the payment is not marked paid and PayMongo's retry redoes
                // all of it (a committed "paid" would make the retry a no-op).
                $this->audit->log('payment.paid', $locked, null, ['amount' => $locked->amount, 'method' => $method]);

                PaymentPaid::dispatch($locked);

                if ($locked->order_id) {
                    $this->orders->markPaid($locked->order);

                    return true;
                }

                $booking = $locked->booking;

                if ($booking->canTransitionTo(Booking::CONFIRMED)) {
                    $this->bookings->transition($booking, Booking::CONFIRMED);
                } else {
                    // e.g. cancelled while the guest was paying — host must refund.
                    $this->audit->log('payment.paid_on_inactive_booking', $locked, null, ['booking_status' => $booking->status]);
                }

                return true;
            });

            return $payment->refresh();
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
