<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\CommissionRate;
use App\Modules\Wallet\Models\Payout;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletService;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PayMongoFake;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 08 (Host wallet & commissions).
|
| Coverage: commission split on a paid payment (default / global / listing
| / promotional priority), idempotency, pending → available on check-out
| and no-show, reversal on refund (from either bucket), payouts (limits,
| admin paid / reject, no double settlement), ledger = balances,
| permissions and the admin screens.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    PayMongoFake::configure();
});

function platformAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->syncWithoutDetaching([
        Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail()->id => ['tenant_id' => null],
    ]);

    return $user;
}

/** Wallet of a tenant, read outside any tenant session. */
function walletOf($tenant): Wallet
{
    return Wallet::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->firstOrFail();
}

function assertLedgerMatches(Wallet $wallet): void
{
    $sum = fn (string $bucket) => round((float) WalletTransaction::query()->withoutGlobalScope('tenant')
        ->where('wallet_id', $wallet->id)->where('bucket', $bucket)->sum('amount'), 2);

    expect($sum('pending'))->toBe(round((float) $wallet->pending_balance, 2))
        ->and($sum('available'))->toBe(round((float) $wallet->available_balance, 2));
}

it('splits a paid booking at the default 10% into platform fee and pending host earnings', function () {
    [, , , $tenant] = PayMongoFake::paidBooking();

    $commission = Commission::query()->withoutGlobalScope('tenant')->sole();
    $wallet = walletOf($tenant);

    expect((float) $commission->gross)->toBe(12500.0)
        ->and((float) $commission->platform_fee)->toBe(1250.0)
        ->and((float) $commission->host_amount)->toBe(11250.0)
        ->and($commission->status)->toBe(Commission::PENDING)
        ->and((float) $wallet->pending_balance)->toBe(11250.0)
        ->and((float) $wallet->available_balance)->toBe(0.0);

    assertLedgerMatches($wallet);
});

it('records one commission per payment even if the earning is replayed', function () {
    [, , , $tenant] = PayMongoFake::paidBooking();

    app(WalletService::class)->recordEarning(Payment::query()->withoutGlobalScope('tenant')->sole());

    expect(Commission::query()->withoutGlobalScope('tenant')->count())->toBe(1)
        ->and((float) walletOf($tenant)->pending_balance)->toBe(11250.0);
});

it('resolves promotional, listing and global rates in priority order', function () {
    [, , $property] = BookingFixtures::hotel();

    expect(CommissionRate::resolveFor($property, '2030-09-05'))->toBeNull(); // config default

    CommissionRate::create(['kind' => 'global', 'rate' => 15]);
    expect((float) CommissionRate::resolveFor($property, '2030-09-05')->rate)->toBe(15.0);

    CommissionRate::create(['kind' => 'listing', 'rate' => 8, 'rateable_type' => 'property', 'rateable_id' => $property->id]);
    expect((float) CommissionRate::resolveFor($property, '2030-09-05')->rate)->toBe(8.0);

    CommissionRate::create(['kind' => 'promotional', 'rate' => 5, 'starts_on' => '2030-09-01', 'ends_on' => '2030-09-30']);
    expect((float) CommissionRate::resolveFor($property, '2030-09-05')->rate)->toBe(5.0)
        ->and((float) CommissionRate::resolveFor($property, '2030-10-05')->rate)->toBe(8.0); // outside the window

    CommissionRate::create(['kind' => 'promotional', 'rate' => 3, 'rateable_type' => 'property', 'rateable_id' => $property->id,
        'starts_on' => '2030-09-01', 'ends_on' => '2030-09-30']);
    expect((float) CommissionRate::resolveFor($property, '2030-09-05')->rate)->toBe(3.0);
});

it('lets the Super Admin set the global and listing rates used for new payments', function () {
    $admin = platformAdmin();

    $this->actingAs($admin)->post(route('admin.commissions.store'), ['kind' => 'global', 'rate' => 12])->assertSessionHasNoErrors();
    $this->post(route('admin.commissions.store'), ['kind' => 'global', 'rate' => 20])->assertSessionHasNoErrors();

    expect(CommissionRate::query()->where('kind', 'global')->count())->toBe(1);

    $this->post(route('admin.commissions.store'), ['kind' => 'listing', 'rate' => 7, 'listing_type' => 'property', 'listing_slug' => 'nope'])
        ->assertSessionHasErrors('listing_slug');

    $this->travelTo(now()); // payment date = today
    [, , , $tenant] = PayMongoFake::paidBooking();

    expect((float) Commission::query()->withoutGlobalScope('tenant')->sole()->platform_fee)->toBe(2500.0);

    $this->actingAs($admin)->get(route('admin.commissions.index'))->assertOk()->assertSee('2,500.00');
});

it('releases earnings to the available balance when the guest checks out', function () {
    [, $booking, $owner, $tenant] = PayMongoFake::paidBooking();
    PropertyManagementFixtures::login($owner, $tenant);

    $this->travelTo('2030-09-05 15:00');
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_in']);
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_out']);

    $wallet = walletOf($tenant);

    expect((float) $wallet->pending_balance)->toBe(0.0)
        ->and((float) $wallet->available_balance)->toBe(11250.0)
        ->and(Commission::query()->withoutGlobalScope('tenant')->sole()->status)->toBe(Commission::RELEASED);

    assertLedgerMatches($wallet);
});

it('takes back pending earnings when a paid booking is refunded before the stay', function () {
    [, $booking, $owner, $tenant] = PayMongoFake::paidBooking();
    PropertyManagementFixtures::login($owner, $tenant);

    $this->post(route('bookings.transition', $booking->reference), ['status' => 'cancelled']);
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'refunded'])->assertSessionHasNoErrors();

    $wallet = walletOf($tenant);

    expect((float) $wallet->pending_balance)->toBe(0.0)
        ->and(Commission::query()->withoutGlobalScope('tenant')->sole()->status)->toBe(Commission::REVERSED);

    assertLedgerMatches($wallet);
});

it('takes back released earnings when a no-show is refunded', function () {
    [, $booking, $owner, $tenant] = PayMongoFake::paidBooking();
    PropertyManagementFixtures::login($owner, $tenant);

    $this->travelTo('2030-09-06 09:00');
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'no_show']);
    expect((float) walletOf($tenant)->available_balance)->toBe(11250.0);

    $this->post(route('bookings.transition', $booking->reference), ['status' => 'refunded'])->assertSessionHasNoErrors();

    $wallet = walletOf($tenant);
    expect((float) $wallet->available_balance)->toBe(0.0);
    assertLedgerMatches($wallet);
});

it('handles payout requests and their settlement by the Super Admin', function () {
    [, $booking, $owner, $tenant] = PayMongoFake::paidBooking();
    PropertyManagementFixtures::login($owner, $tenant);
    $this->travelTo('2030-09-05 15:00');
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_in']);
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_out']);

    $destination = ['method' => 'gcash', 'account_name' => 'Hotel A', 'account_number' => '09171234567'];

    $this->post(route('wallet.payouts.store'), ['amount' => 50] + $destination)->assertSessionHasErrors('amount');
    $this->post(route('wallet.payouts.store'), ['amount' => 20000] + $destination)->assertSessionHasErrors('amount');
    $this->post(route('wallet.payouts.store'), ['amount' => 5000] + $destination)->assertSessionHasNoErrors();
    $this->post(route('wallet.payouts.store'), ['amount' => 6000] + $destination)->assertSessionHasNoErrors();

    expect((float) walletOf($tenant)->available_balance)->toBe(250.0);

    [$first, $second] = Payout::query()->withoutGlobalScope('tenant')->orderBy('id')->get();
    $admin = platformAdmin();

    $this->actingAs($admin)->get(route('admin.payouts.index'))->assertOk()->assertSee('09171234567');
    $this->patch(route('admin.payouts.update', $first->id), ['status' => 'paid'])->assertSessionHasErrors('reference');
    $this->patch(route('admin.payouts.update', $first->id), ['status' => 'paid', 'reference' => 'GC-778'])->assertSessionHasNoErrors();
    $this->patch(route('admin.payouts.update', $second->id), ['status' => 'rejected', 'note' => 'Wrong number'])->assertSessionHasNoErrors();
    $this->patch(route('admin.payouts.update', $second->id), ['status' => 'paid', 'reference' => 'X'])->assertSessionHasErrors('amount');

    $wallet = walletOf($tenant);

    expect($first->refresh()->status)->toBe(Payout::PAID)
        ->and($first->reference)->toBe('GC-778')
        ->and($second->refresh()->status)->toBe(Payout::REJECTED)
        ->and((float) $wallet->available_balance)->toBe(6250.0);

    assertLedgerMatches($wallet);
});

it('limits the wallet to owners and managers and payouts to owners', function () {
    [$owner, $tenant] = MarketplaceFixtures::business();
    PropertyManagementFixtures::login($owner, $tenant);
    $this->get(route('wallet.index'))->assertOk();

    $manager = MarketplaceFixtures::member($tenant, 'manager');
    PropertyManagementFixtures::login($manager, $tenant);
    $this->get(route('wallet.index'))->assertOk()->assertDontSee('Request a payout');
    $this->post(route('wallet.payouts.store'), ['amount' => 100, 'method' => 'bank', 'account_name' => 'x', 'account_number' => '1'])->assertForbidden();

    $desk = MarketplaceFixtures::member($tenant, 'front_desk');
    PropertyManagementFixtures::login($desk, $tenant);
    $this->get(route('wallet.index'))->assertForbidden();

    $this->get(route('admin.commissions.index'))->assertForbidden();
    $this->get(route('admin.payouts.index'))->assertForbidden();
});

it('rolls the payment back if recording the earning fails, so the webhook retry completes it', function () {
    $failOnce = true;
    Illuminate\Support\Facades\Event::listen(App\Modules\Payments\Events\PaymentPaid::class, function () use (&$failOnce) {
        if ($failOnce) {
            $failOnce = false;
            throw new RuntimeException('wallet temporarily down');
        }
    });

    [$guest, $booking, , $tenant] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: true);

    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertStatus(500);

    expect(Payment::forCustomer($guest)->sole()->status)->toBe(Payment::PENDING)
        ->and($booking->refresh()->status)->toBe('pending')
        ->and(Commission::query()->withoutGlobalScope('tenant')->count())->toBe(0);

    // PayMongo retries the same (unprocessed) event.
    PayMongoFake::webhook('evt_1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    expect(Payment::forCustomer($guest)->sole()->status)->toBe(Payment::PAID)
        ->and($booking->refresh()->status)->toBe('confirmed')
        ->and((float) walletOf($tenant)->pending_balance)->toBe(11250.0);
});
