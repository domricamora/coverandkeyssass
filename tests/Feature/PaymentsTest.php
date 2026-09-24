<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\BookingFixtures;
use Tests\Support\PayMongoFake;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 07 (PayMongo).
|
| PayMongo is faked with Http::fake(). Coverage: checkout creation + reuse,
| disabled mode, webhook signature (bad / stale), server-side verification
| (webhook and return URL never confirm on their own), amount mismatch,
| idempotency (replayed and re-sent events), failed payments + retry,
| refunds through the booking state machine (success and provider
| refusal), paid-after-cancel, and the customer transaction history.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    PayMongoFake::configure();
});

it('sends the guest to a PayMongo checkout for the booking total and reuses it', function () {
    [$owner, $tenant, $property, $type] = BookingFixtures::hotel();
    $guest = User::factory()->create();
    $booking = BookingFixtures::reserve($property, $type, [], Booking::SOURCE_MARKETPLACE, $guest);
    PayMongoFake::fake();

    $this->actingAs($guest)
        ->get(route('account.bookings.show', $booking->reference))
        ->assertSee('with PayMongo');

    $this->post(route('account.payments.pay', $booking->reference))
        ->assertRedirect('https://checkout.paymongo.test/cs_test_1');
    $this->post(route('account.payments.pay', $booking->reference))
        ->assertRedirect('https://checkout.paymongo.test/cs_test_1');

    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_secret:'))
        && $request['data']['attributes']['line_items'][0]['amount'] === 1250000
        && $request['data']['attributes']['reference_number'] === $booking->reference);

    expect(Payment::forCustomer($guest)->sole())
        ->status->toBe(Payment::PENDING)
        ->checkout_session_id->toBe('cs_test_1');
});

it('offers no online payment when PayMongo is not configured', function () {
    config(['services.paymongo.secret_key' => null]);
    [, , $property, $type] = BookingFixtures::hotel();
    $guest = User::factory()->create();
    $booking = BookingFixtures::reserve($property, $type, [], Booking::SOURCE_MARKETPLACE, $guest);

    $this->actingAs($guest)->get(route('account.bookings.show', $booking->reference))->assertDontSee('with PayMongo');
    $this->post(route('account.payments.pay', $booking->reference))->assertNotFound();
});

it('rejects webhooks with a bad or stale signature', function () {
    [, $booking] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: true);

    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'], 't='.time().',te=forged,li=')->assertStatus(400);

    $old = time() - 3600;
    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'],
        't='.$old.',te='.hash_hmac('sha256', $old.'.x', 'whsk_test_secret'))->assertStatus(400);

    expect($booking->refresh()->status)->toBe(Booking::PENDING)
        ->and(DB::table('payment_events')->count())->toBe(0);
});

it('confirms the booking once PayMongo reports the payment paid, exactly once', function () {
    [$guest, $booking] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: true);

    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    expect($booking->refresh()->status)->toBe(Booking::CONFIRMED)
        ->and(Payment::forCustomer($guest)->sole())
        ->status->toBe(Payment::PAID)
        ->provider_payment_id->toBe('pay_test_1')
        ->method->toBe('gcash');

    // Replayed event, and PayMongo re-sending under a new event id.
    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk()->assertSee('Already processed');
    PayMongoFake::webhook('evt_2', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    expect(Payment::forCustomer($guest)->count())->toBe(1)
        ->and($guest->notifications()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'payment.paid')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'booking.confirmed')->count())->toBe(1);
});

it('does not trust the webhook body: an unpaid session confirms nothing', function () {
    [, $booking] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: false);

    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1', 'attributes' => ['payments' => [['attributes' => ['status' => 'paid']]]]])->assertOk();

    expect($booking->refresh()->status)->toBe(Booking::PENDING);
});

it('refuses a payment whose amount does not match the booking', function () {
    [, $booking] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: true, amount: 100);

    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    expect($booking->refresh()->status)->toBe(Booking::PENDING)
        ->and(AuditLog::query()->where('action', 'payment.amount_mismatch')->exists())->toBeTrue();
});

it('verifies the return URL with PayMongo instead of trusting the redirect', function () {
    [$guest, $booking] = PayMongoFake::pendingPaidFlow();

    PayMongoFake::fake(paid: false);
    $this->get(route('account.payments.return', $booking->reference))->assertSessionHas('warning');
    expect($booking->refresh()->status)->toBe(Booking::PENDING);

    PayMongoFake::fake(paid: true);
    $this->get(route('account.payments.return', $booking->reference))->assertSessionHas('success');
    expect($booking->refresh()->status)->toBe(Booking::CONFIRMED);
});

it('records a failed payment and lets the guest try again', function () {
    [$guest, $booking] = PayMongoFake::pendingPaidFlow();

    PayMongoFake::webhook('evt_f', 'payment.failed', ['id' => 'pay_x', 'attributes' => [
        'payment_intent_id' => 'pi_test_1', 'failed_message' => 'Card declined',
    ]])->assertOk();

    expect(Payment::forCustomer($guest)->sole())
        ->status->toBe(Payment::FAILED)
        ->failure_reason->toBe('Card declined')
        ->and($booking->refresh()->status)->toBe(Booking::PENDING);

    PayMongoFake::fake();
    $this->post(route('account.payments.pay', $booking->reference))->assertRedirect();

    expect(Payment::forCustomer($guest)->where('status', Payment::PENDING)->count())->toBe(1);
});

it('refunds through PayMongo when the host marks a cancelled booking refunded', function () {
    [$guest, $booking, $owner, $tenant] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: true);
    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1']);

    PropertyManagementFixtures::login($owner, $tenant);
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'refunded'])->assertSessionHasNoErrors();

    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/refunds')
        && $request['data']['attributes']['payment_id'] === 'pay_test_1'
        && $request['data']['attributes']['amount'] === 1250000);

    expect($booking->refresh()->status)->toBe(Booking::REFUNDED)
        ->and(Payment::forCustomer($guest)->sole())
        ->status->toBe(Payment::REFUNDED)
        ->refund_id->toBe('ref_test_1');
});

it('keeps the booking unrefunded when PayMongo refuses the refund', function () {
    [$guest, $booking, $owner, $tenant] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: true);
    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1']);

    PropertyManagementFixtures::login($owner, $tenant);
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'cancelled']);

    PayMongoFake::fake(paid: true, refundStatus: 400);
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'refunded'])
        ->assertSessionHasErrors('payment');

    expect($booking->refresh()->status)->toBe(Booking::CANCELLED)
        ->and(Payment::forCustomer($guest)->sole()->status)->toBe(Payment::PAID);
});

it('records a payment that lands after cancellation without reviving the booking', function () {
    [$guest, $booking, $owner, $tenant] = PayMongoFake::pendingPaidFlow();

    PropertyManagementFixtures::login($owner, $tenant);
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'cancelled']);

    PayMongoFake::fake(paid: true);
    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    expect($booking->refresh()->status)->toBe(Booking::CANCELLED)
        ->and(Payment::forCustomer($guest)->sole()->status)->toBe(Payment::PAID)
        ->and(AuditLog::query()->where('action', 'payment.paid_on_inactive_booking')->exists())->toBeTrue();
});

it('lists a guest their own payments only', function () {
    [$guest, $booking] = PayMongoFake::pendingPaidFlow();
    $other = User::factory()->create();

    $this->actingAs($guest)->get(route('account.payments.index'))->assertOk()->assertSee($booking->reference);
    $this->actingAs($other)->get(route('account.payments.index'))->assertOk()->assertDontSee($booking->reference);
});
