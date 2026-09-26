<?php

namespace App\Modules\Payments\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin PayPal REST client (Orders v2, https://developer.paypal.com/docs/api/orders/v2/).
 * Sandbox or live by `services.paypal.mode`. Amounts are decimal strings
 * ("1234.00"). PayPal only moves money when we call capture on the return
 * URL, so no webhook is needed for the happy path.
 */
class PayPalGateway
{
    public static function enabled(): bool
    {
        return filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret'));
    }

    /** @return array{id: string, approve_url: string} */
    public function createOrder(string $reference, string $description, float $amount, string $currency, string $returnUrl, string $cancelUrl): array
    {
        $order = $this->http()->post('v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'custom_id' => $reference,
                'description' => mb_substr($description, 0, 127),
                'amount' => ['currency_code' => strtoupper($currency), 'value' => number_format($amount, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => mb_substr((string) config('app.name'), 0, 127),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ]]],
        ])->throw()->json();

        $approve = collect($order['links'] ?? [])->first(fn ($l) => in_array($l['rel'] ?? '', ['payer-action', 'approve'], true));

        return ['id' => (string) $order['id'], 'approve_url' => (string) ($approve['href'] ?? '')];
    }

    /**
     * Capture an approved order; an order that is already captured (or not
     * approved yet) is returned as PayPal reports it. Never trusts the caller.
     *
     * @return array<string, mixed> the order resource
     */
    public function capture(string $orderId): array
    {
        $order = $this->getOrder($orderId);

        if (($order['status'] ?? null) !== 'APPROVED') {
            return $order; // COMPLETED already, or the buyer has not approved yet
        }

        try {
            return $this->http()->withHeader('PayPal-Request-Id', 'capture-'.$orderId)
                ->withBody('{}', 'application/json')
                ->post('v2/checkout/orders/'.rawurlencode($orderId).'/capture')->throw()->json();
        } catch (RequestException $e) {
            if (data_get($e->response->json(), 'details.0.issue') === 'ORDER_ALREADY_CAPTURED') {
                return $this->getOrder($orderId);
            }

            throw $e;
        }
    }

    /** @return array<string, mixed> */
    public function getOrder(string $orderId): array
    {
        return $this->http()->get('v2/checkout/orders/'.rawurlencode($orderId))->throw()->json();
    }

    /** @return array<string, mixed> the refund resource */
    public function refund(string $captureId, float $amount, string $currency, string $note): array
    {
        return $this->http()->withHeader('PayPal-Request-Id', 'refund-'.$captureId)
            ->post('v2/payments/captures/'.rawurlencode($captureId).'/refund', [
                'amount' => ['currency_code' => strtoupper($currency), 'value' => number_format($amount, 2, '.', '')],
                'note_to_payer' => mb_substr($note, 0, 255),
            ])->throw()->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())->withToken($this->token())->acceptJson()->asJson()->timeout(20);
    }

    /** OAuth client-credentials token, cached until shortly before it expires. */
    private function token(): string
    {
        $key = 'paypal.token.'.md5(config('services.paypal.client_id').config('services.paypal.mode'));

        if ($token = Cache::get($key)) {
            return $token;
        }

        $response = Http::baseUrl($this->baseUrl())->asForm()->acceptJson()->timeout(20)
            ->withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.secret'))
            ->post('v1/oauth2/token', ['grant_type' => 'client_credentials'])->throw()->json();

        Cache::put($key, $response['access_token'], max(60, (int) ($response['expires_in'] ?? 3600) - 120));

        return $response['access_token'];
    }

    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}
