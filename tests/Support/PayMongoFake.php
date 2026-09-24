<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Fake PayMongo API + signed webhooks for the Phase 07/08 tests.
 *
 * Http::fake stubs stack first-match-wins, so the fake is registered once
 * per test and answers from mutable state that fake() updates.
 */
final class PayMongoFake
{
    private static array $state = [];

    private static bool $registered = false;

    public static function configure(): void
    {
        self::$state = ['paid' => false, 'amount' => 1250000, 'refundStatus' => 200, 'sessions' => 0];
        self::$registered = false;

        config([
            'services.paymongo.secret_key' => 'sk_test_secret',
            'services.paymongo.webhook_secret' => 'whsk_test_secret',
            'services.paymongo.base_url' => 'https://api.paymongo.test/v1',
        ]);
    }

    /** $paid decides what GET checkout_sessions/{id} reports. */
    public static function fake(bool $paid = false, int $amount = 1250000, int $refundStatus = 200): void
    {
        self::$state = compact('paid', 'amount', 'refundStatus') + ['sessions' => self::$state['sessions'] ?? 0];

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        Http::fake(fn (HttpRequest $request) => match (true) {
            str_ends_with($request->url(), '/checkout_sessions') => Http::response(self::session('cs_test_'.++self::$state['sessions'])),
            str_contains($request->url(), '/checkout_sessions/') => Http::response(self::session(basename($request->url()), self::$state['paid'], self::$state['amount'])),
            str_ends_with($request->url(), '/refunds') => self::$state['refundStatus'] === 200
                ? Http::response(['data' => ['id' => 'ref_test_1', 'attributes' => ['status' => 'pending']]])
                : Http::response(['errors' => [['detail' => 'Payment cannot be refunded']]], self::$state['refundStatus']),
        });
    }

    public static function session(string $id, bool $paid = false, int $amount = 1250000): array
    {
        return ['data' => [
            'id' => $id,
            'type' => 'checkout_session',
            'attributes' => [
                'checkout_url' => 'https://checkout.paymongo.test/'.$id,
                'payment_intent' => ['id' => 'pi_test_1'],
                'payment_method_used' => $paid ? 'gcash' : null,
                'payments' => $paid ? [[
                    'id' => 'pay_test_1',
                    'type' => 'payment',
                    'attributes' => ['amount' => $amount, 'currency' => 'PHP', 'status' => 'paid'],
                ]] : [],
            ],
        ]];
    }

    /**
     * Guest with a pending marketplace booking (PHP 12,500) and an open
     * checkout (cs_test_1).
     *
     * @return array{0: User, 1: Booking, 2: User, 3: \App\Models\Tenant}
     */
    public static function pendingPaidFlow(): array
    {
        [$owner, $tenant, $property, $type] = BookingFixtures::hotel();
        $guest = User::factory()->create();
        $booking = BookingFixtures::reserve($property, $type, [], Booking::SOURCE_MARKETPLACE, $guest);

        self::fake();
        test()->actingAs($guest)->post(route('account.payments.pay', $booking->reference));

        return [$guest, $booking, $owner, $tenant];
    }

    /** Same, then PayMongo reports it paid through a signed webhook. */
    public static function paidBooking(): array
    {
        $flow = self::pendingPaidFlow();

        self::fake(paid: true);
        self::webhook('evt_paid', 'checkout_session.payment.paid', ['id' => 'cs_test_1']);

        return $flow;
    }

    public static function webhook(string $eventId, string $type, array $resource, ?string $signature = null): TestResponse
    {
        $body = json_encode(['data' => ['id' => $eventId, 'type' => 'event', 'attributes' => [
            'type' => $type, 'livemode' => false, 'data' => $resource,
        ]]]);

        $t = time();
        $signature ??= 't='.$t.',te='.hash_hmac('sha256', $t.'.'.$body, 'whsk_test_secret').',li=';

        return test()->call('POST', route('payments.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $signature,
        ], $body);
    }
}
