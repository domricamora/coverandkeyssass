<?php

namespace App\Modules\Payments\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin PayMongo REST client (https://developers.paymongo.com). Amounts are
 * in centavos. HTTP errors throw (RequestException) — callers decide how
 * to surface them.
 */
class PayMongoGateway
{
    /** @return array<string, mixed> the checkout_session resource (`data`) */
    public function createCheckoutSession(array $attributes): array
    {
        return $this->http()->post('checkout_sessions', ['data' => ['attributes' => $attributes]])->throw()->json('data');
    }

    /** @return array<string, mixed> */
    public function retrieveCheckoutSession(string $id): array
    {
        return $this->http()->get('checkout_sessions/'.rawurlencode($id))->throw()->json('data');
    }

    /** @return array<string, mixed> the refund resource */
    public function createRefund(string $paymentId, int $amount, string $reason = 'requested_by_customer', ?string $notes = null): array
    {
        return $this->http()->post('refunds', ['data' => ['attributes' => array_filter([
            'payment_id' => $paymentId,
            'amount' => $amount,
            'reason' => $reason,
            'notes' => $notes,
        ])]])->throw()->json('data');
    }

    /**
     * Verify a `Paymongo-Signature` header: `t=<unix>,te=<test sig>,li=<live sig>`
     * where sig = HMAC-SHA256("<t>.<raw body>", webhook secret). Stale
     * timestamps are rejected to stop replays of captured requests.
     */
    public function validSignature(string $payload, ?string $header): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');

        if ($secret === '' || blank($header)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > config('services.paymongo.tolerance', 300)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach (['te', 'li'] as $mode) {
            if (($parts[$mode] ?? '') !== '' && hash_equals($expected, $parts[$mode])) {
                return true;
            }
        }

        return false;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.paymongo.base_url'), '/').'/')
            ->withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
