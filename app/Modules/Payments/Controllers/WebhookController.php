<?php

namespace App\Modules\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PayMongoGateway;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * POST /webhooks/paymongo — outside the `web` group (no session / CSRF);
 * authenticity comes from the HMAC signature instead.
 *
 * Idempotency: each event id is stored once in `payment_events`; an event
 * already processed is acknowledged (200) and ignored. Paid events are not
 * trusted as-is — PaymentService::sync() re-reads the checkout session
 * from PayMongo before anything is recorded.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PayMongoGateway $gateway,
        private readonly PaymentService $payments,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();

        if (! $this->gateway->validSignature($payload, $request->header('Paymongo-Signature'))) {
            return response('Invalid signature', 400);
        }

        $eventId = (string) $request->input('data.id');
        $type = (string) $request->input('data.attributes.type');
        $resource = (array) $request->input('data.attributes.data', []);

        if ($eventId === '' || $type === '') {
            return response('Malformed event', 400);
        }

        DB::table('payment_events')->insertOrIgnore([
            'event_id' => $eventId,
            'type' => $type,
            'payload' => $payload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (DB::table('payment_events')->where('event_id', $eventId)->whereNotNull('processed_at')->exists()) {
            return response('Already processed', 200);
        }

        match ($type) {
            'checkout_session.payment.paid' => $this->sync(Payment::byProvider()->where('checkout_session_id', $resource['id'] ?? '')->first()),
            'payment.paid' => $this->sync(Payment::byProvider()->where('payment_intent_id', data_get($resource, 'attributes.payment_intent_id', ''))->first()),
            'payment.failed' => ($payment = Payment::byProvider()->where('payment_intent_id', data_get($resource, 'attributes.payment_intent_id', ''))->first())
                ? $this->payments->markFailed($payment, data_get($resource, 'attributes.failed_message'))
                : null,
            default => null, // acknowledged, nothing to do
        };

        DB::table('payment_events')->where('event_id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);

        return response('OK', 200);
    }

    private function sync(?Payment $payment): void
    {
        if ($payment) {
            $this->payments->sync($payment);
        }
    }
}
