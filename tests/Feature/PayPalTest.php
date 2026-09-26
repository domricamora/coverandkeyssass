<?php

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;

/*
| PayPal (Orders v2, sandbox) next to PayMongo: guest checkout without an
| account, pay now at the review step, capture on the return URL, exact
| amount check, buyer who cancels, and refunds.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    config(['services.paypal.client_id' => 'client', 'services.paypal.secret' => 'secret', 'services.paypal.mode' => 'sandbox', 'services.paymongo.secret_key' => null]);

    // Mutable PayPal state the fake answers from: status APPROVED|CREATED|COMPLETED, captured amount.
    $this->paypal = (object) ['status' => 'APPROVED', 'value' => '7000.00', 'refunds' => 0];
    Http::fake(function (HttpRequest $r) {
        $p = $this->paypal;
        $order = fn (string $status) => ['id' => 'PP-ORDER-1', 'status' => $status, 'purchase_units' => [['payments' => ['captures' => $status === 'COMPLETED'
            ? [['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'PHP', 'value' => $p->value]]] : []]]]];

        return match (true) {
            str_ends_with($r->url(), '/v1/oauth2/token') => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            str_ends_with($r->url(), '/v2/checkout/orders') => Http::response(['id' => 'PP-ORDER-1', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PP-ORDER-1']]], 201),
            str_ends_with($r->url(), '/capture') => Http::response($order($p->status = 'COMPLETED'), 201),
            str_contains($r->url(), '/v2/checkout/orders/') => Http::response($order($p->status)),
            str_ends_with($r->url(), '/refund') => Http::response(['id' => 'REF-'.++$p->refunds, 'status' => 'COMPLETED'], 201),
        };
    });

    [, , $this->property, $this->type] = BookingFixtures::hotel(rooms: 2);
    MarketplaceFixtures::publish($this->property);
    auth()->logout();
    $this->stay = ['check_in' => '2030-09-02', 'check_out' => '2030-09-04', 'room_type_id' => $this->type->id, 'quantity' => 1, 'adults' => 2];
});

it('books without registering and pays with PayPal, confirmed only after capture', function () {
    expect(PaymentService::providers())->toHaveCount(1)->and(PaymentService::providers()[0][0])->toBe('paypal');

    $this->postJson(route('guest.identify'), ['name' => 'Lia Santos', 'email' => 'lia@example.test'])->assertJsonPath('status', 'signed_in');

    $this->post(route('marketplace.properties.reserve', $this->property->slug), $this->stay + ['pay' => 'paypal'])
        ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PP-ORDER-1');

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v2/checkout/orders')
        && $r['purchase_units'][0]['amount'] === ['currency_code' => 'PHP', 'value' => '7000.00']
        && str_contains($r['payment_source']['paypal']['experience_context']['return_url'], '/payment-return'));

    $booking = Booking::query()->withoutGlobalScopes()->sole();
    $payment = Payment::query()->withoutGlobalScopes()->sole();
    expect($booking->customer->email)->toBe('lia@example.test')
        ->and($booking->status)->toBe(Booking::PENDING)
        ->and($payment->provider)->toBe('paypal')
        ->and($payment->status)->toBe(Payment::PENDING);

    // Return URL (PayPal appends token + PayerID): capture server-side, then confirm.
    $this->get(route('account.payments.return', $booking->reference).'?token=PP-ORDER-1&PayerID=X')->assertRedirect(route('account.bookings.show', $booking->reference));

    expect($payment->refresh()->status)->toBe(Payment::PAID)
        ->and($payment->provider_payment_id)->toBe('CAP-1')
        ->and($payment->method)->toBe('paypal')
        ->and($booking->refresh()->status)->toBe(Booking::CONFIRMED);
});

it('keeps the booking pending when the buyer has not approved, and refuses a wrong amount', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('marketplace.properties.reserve', $this->property->slug), $this->stay + ['pay' => 'paypal'])->assertRedirect();
    $booking = Booking::query()->withoutGlobalScopes()->sole();
    $payment = Payment::query()->withoutGlobalScopes()->sole();

    $this->paypal->status = 'PAYER_ACTION_REQUIRED'; // came back via cancel / never approved
    $this->get(route('account.payments.return', $booking->reference));
    expect($payment->refresh()->status)->toBe(Payment::PENDING)->and($booking->refresh()->status)->toBe(Booking::PENDING);

    $this->paypal->status = 'APPROVED';
    $this->paypal->value = '1.00'; // capture for another amount
    $this->get(route('account.payments.return', $booking->reference));
    expect($payment->refresh()->status)->toBe(Payment::PENDING)->and($booking->refresh()->status)->toBe(Booking::PENDING);
});

it('pays at the property when the guest chooses so, and refunds PayPal payments through PayPal', function () {
    $this->actingAs(User::factory()->create());
    $this->post(route('marketplace.properties.reserve', $this->property->slug), $this->stay + ['pay' => 'later'])
        ->assertRedirect(route('account.bookings.show', Booking::query()->withoutGlobalScopes()->sole()->reference));
    expect(Payment::query()->withoutGlobalScopes()->count())->toBe(0);

    $this->post(route('marketplace.properties.reserve', $this->property->slug), $this->stay + ['pay' => 'paypal']);
    $booking = Booking::query()->withoutGlobalScopes()->latest('id')->first();
    $this->get(route('account.payments.return', $booking->reference));
    expect($booking->refresh()->status)->toBe(Booking::CONFIRMED);

    app(BookingService::class)->asTenantOf($booking, fn () => app(PaymentService::class)->refundBooking($booking));
    expect(Payment::query()->withoutGlobalScopes()->where('booking_id', $booking->id)->sole())
        ->status->toBe(Payment::REFUNDED)->refund_id->toBe('REF-1');
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v2/payments/captures/CAP-1/refund') && $r['amount']['value'] === '7000.00');
});
