<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Notifications\BillingNotice;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Support\Usage;
use App\Support\ModuleService;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PayMongoFake;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 27 (SaaS Billing) — per-module subscriptions (monthly / yearly),
| trial proration, coupons, renewals, overdue → suspension → restore,
| PayMongo and manual payment, mid-period changes, trial expiry, usage
| limits, permissions and isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    PropertyManagementFixtures::bootstrap();
    PayMongoFake::configure();
    $this->travelTo('2026-10-01 00:00:00');
    Module::query()->where('slug', 'property')->update(['trial_days' => 0]);
    Module::query()->where('slug', 'booking')->update(['trial_days' => 14]);
});

function mod(string $slug): Module
{
    return Module::query()->where('slug', $slug)->firstOrFail();
}

function billing(): BillingService
{
    return app(BillingService::class);
}

function enabled(Tenant $tenant, string $slug): bool
{
    return app(ModuleService::class)->isEnabled(mod($slug), $tenant);
}

it('subscribes to modules with dependencies, charging trials only from the trial end', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');

    $subscription = billing()->subscribe($tenant, [mod('booking')->id], 'monthly', null, $owner);

    expect($subscription->items()->pluck('module_id')->sort()->values()->all())->toBe(collect([mod('property')->id, mod('booking')->id])->sort()->values()->all())
        ->and(enabled($tenant, 'property'))->toBeTrue()
        ->and(enabled($tenant, 'booking'))->toBeTrue()
        ->and($subscription->current_period_end->toDateString())->toBe('2026-11-01');

    $invoice = $subscription->invoices()->sole();
    $lines = $invoice->items->keyBy('module_id');
    // Booking ₱1,999 trials until Oct 15 → 17 of 31 days; Property ₱1,499 in full.
    expect($lines[mod('property')->id]->amount_cents)->toBe(149900)
        ->and($lines[mod('booking')->id]->amount_cents)->toBe((int) round(199900 * 17 / 31))
        ->and($lines[mod('booking')->id]->description)->toContain('trial ends')
        ->and($invoice->total_cents)->toBe(149900 + (int) round(199900 * 17 / 31))
        ->and($invoice->status)->toBe(Invoice::OPEN)
        ->and($owner->notifications()->where('type', BillingNotice::class)->count())->toBe(1);

    // Yearly is ten months' price.
    expect(billing()->priceCents($tenant, mod('booking'), 'yearly'))->toBe(1999000);

    // A second subscription is refused.
    expect(fn () => billing()->subscribe($tenant, [mod('crm')->id], 'monthly', null, $owner))->toThrow(ValidationException::class);
});

it('renews each period once, applies coupons for their cycles and limits redemptions', function () {
    Coupon::create(['code' => 'LAUNCH20', 'name' => 'Launch', 'percent_off' => 20, 'duration' => 'repeating', 'duration_cycles' => 2, 'max_redemptions' => 1]);
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');
    [$other, $otherTenant] = MarketplaceFixtures::business('Hotel XYZ');

    $subscription = billing()->subscribe($tenant, [mod('property')->id], 'monthly', 'launch20', $owner);
    expect($subscription->invoices()->sole())->discount_cents->toBe(29980)->total_cents->toBe(119920);

    // The only redemption is gone.
    expect(fn () => billing()->subscribe($otherTenant, [mod('property')->id], 'monthly', 'LAUNCH20', $other))->toThrow(ValidationException::class)
        ->and(Subscription::query()->where('tenant_id', $otherTenant->id)->exists())->toBeFalse();

    $this->travelTo('2026-11-01 06:00:00');
    billing()->run();
    billing()->run();
    $this->travelTo('2026-12-01 06:00:00');
    billing()->run();

    $invoices = $subscription->invoices()->get()->sortBy('id')->values();
    expect($invoices)->toHaveCount(3)
        ->and($invoices[1]->period_start->toDateString())->toBe('2026-11-01')
        ->and($invoices[1]->discount_cents)->toBe(29980)   // 2nd cycle
        ->and($invoices[2]->discount_cents)->toBe(0)       // coupon used up
        ->and($invoices[2]->total_cents)->toBe(149900)
        ->and($subscription->refresh()->current_period_end->toDateString())->toBe('2027-01-01');
});

it('marks overdue invoices, suspends after the grace period and restores on payment', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');
    $subscription = billing()->subscribe($tenant, [mod('property')->id], 'monthly', null, $owner);
    $invoice = $subscription->invoices()->sole();

    $this->travelTo('2026-10-09 00:00:00'); // due Oct 8
    billing()->run();
    expect($subscription->refresh()->status)->toBe(Subscription::PAST_DUE)
        ->and(enabled($tenant, 'property'))->toBeTrue()
        ->and($owner->notifications()->get()->where('data.event', 'billing_past_due'))->toHaveCount(1);

    $this->travelTo('2026-10-16 00:00:00');
    billing()->run();
    expect(enabled($tenant, 'property'))->toBeFalse();

    PropertyManagementFixtures::login($owner, $tenant);
    $this->get(route('properties.index'))->assertForbidden();
    $this->get(route('billing.index'))->assertOk()->assertSee('Suspended');

    // PayMongo: checkout, then the signed webhook re-reads the session before recording.
    PayMongoFake::fake(paid: false);
    $this->post(route('billing.invoices.pay', $invoice->number))->assertRedirect('https://checkout.paymongo.test/cs_test_1');
    PayMongoFake::fake(paid: true, amount: 100); // wrong amount → ignored
    PayMongoFake::webhook('evt_b1', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();
    expect($invoice->refresh()->status)->toBe(Invoice::OPEN);

    PayMongoFake::fake(paid: true, amount: 149900);
    PayMongoFake::webhook('evt_b2', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    expect($invoice->refresh())->status->toBe(Invoice::PAID)->payment_method->toBe('paymongo')
        ->and($subscription->refresh()->status)->toBe(Subscription::ACTIVE)
        ->and(enabled($tenant, 'property'))->toBeTrue();
    $this->get(route('properties.index'))->assertOk();
});

it('lets a Super Admin confirm a bank transfer, void invoices and create coupons', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');
    $invoice = billing()->subscribe($tenant, [mod('property')->id], 'monthly', null, $owner)->invoices()->sole();
    $admin = User::factory()->create();
    $admin->assignPlatformRole();

    $this->actingAs($admin)->get(route('admin.billing.index'))->assertOk()->assertSee($invoice->number)->assertSee('Hotel ABC');
    $this->post(route('admin.billing.invoices.paid', $invoice), ['reference' => 'BDO-7781'])->assertRedirect();
    expect($invoice->refresh())->status->toBe(Invoice::PAID)->payment_reference->toBe('BDO-7781')->recorded_by->toBe($admin->id);
    $this->post(route('admin.billing.invoices.void', $invoice))->assertSessionHasErrors('invoice');

    $this->post(route('admin.billing.coupons.store'), ['code' => 'half', 'name' => 'Half', 'percent_off' => 50, 'duration' => 'once'])->assertSessionHasNoErrors();
    expect(Coupon::query()->where('code', 'HALF')->exists())->toBeTrue();
    $this->post(route('admin.billing.coupons.store'), ['code' => 'bad', 'name' => 'Bad', 'duration' => 'once'])->assertSessionHasErrors('percent_off');

    $this->actingAs($owner)->get(route('admin.billing.index'))->assertForbidden();
});

it('adds modules mid-period with a prorated invoice and guards removals', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');
    Module::query()->where('slug', 'crm')->update(['trial_days' => 0]);
    $subscription = billing()->subscribe($tenant, [mod('booking')->id], 'monthly', null, $owner);

    $this->travelTo('2026-10-17 00:00:00'); // 15 of 31 days left
    $invoice = billing()->addModules($subscription, [mod('crm')->id]);
    expect($invoice->items()->sole())->amount_cents->toBe((int) round(89900 * 15 / 31))
        ->and(enabled($tenant, 'crm'))->toBeTrue();

    // Property is needed by Booking.
    expect(fn () => billing()->removeModule($subscription, mod('property')))->toThrow(ValidationException::class);

    billing()->removeModule($subscription, mod('crm'));
    expect(enabled($tenant, 'crm'))->toBeFalse()
        ->and($subscription->items()->count())->toBe(2);

    // Switching to yearly applies at renewal; cancelling ends it at period end.
    billing()->changeInterval($subscription, 'yearly');
    billing()->cancel($subscription);
    $this->travelTo('2026-11-01 06:00:00');
    billing()->run();
    expect($subscription->refresh()->status)->toBe(Subscription::CANCELLED)
        ->and($subscription->invoices()->count())->toBe(2)
        ->and(enabled($tenant, 'booking'))->toBeFalse();
});

it('expires trials of modules nobody pays for, but never Super Admin grants without a trial', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');
    app(ModuleService::class)->enableForTenant(mod('restaurant'), $tenant);                     // 14-day trial
    app(ModuleService::class)->enableForTenant(mod('inventory'), $tenant, ['trial_days' => 0]); // complimentary

    $this->travelTo('2026-10-16 00:00:00');
    MarketplaceFixtures::asTenant(null);
    $this->artisan('billing:run')->assertSuccessful();

    expect(enabled($tenant, 'restaurant'))->toBeFalse()
        ->and(enabled($tenant, 'inventory'))->toBeTrue();

    // Subscribing brings it back without a new trial.
    $invoice = billing()->subscribe($tenant, [mod('restaurant')->id], 'monthly', null, $owner)->invoices()->sole();
    expect(enabled($tenant, 'restaurant'))->toBeTrue()
        ->and($invoice->total_cents)->toBe(149900);
});

it('enforces plan limits with per-business overrides', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');
    app(ModuleService::class)->enableForTenant(mod('restaurant'), $tenant, ['limits' => ['restaurants' => 1]]);
    MarketplaceFixtures::restaurant($tenant, $owner);

    expect(Usage::limit($tenant, 'restaurants'))->toBe(1)
        ->and(Usage::limit($tenant, 'rooms'))->toBe(150)
        ->and(Usage::count($tenant, 'restaurants'))->toBe(1)
        ->and(fn () => Usage::ensureRoom($tenant, 'restaurants'))->toThrow(ValidationException::class);

    PropertyManagementFixtures::login($owner, $tenant);
    $this->post(route('restaurants.store'), ['name' => 'Second'])->assertSessionHasErrors('limit');
    $this->get(route('billing.index'))->assertOk()->assertSee('At limit');
});

it('keeps billing to owners of the business', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel ABC');
    $invoice = billing()->subscribe($tenant, [mod('property')->id], 'monthly', null, $owner)->invoices()->sole();
    $manager = MarketplaceFixtures::member($tenant, 'manager');
    [$stranger, $strangerTenant] = MarketplaceFixtures::business('Hotel XYZ');

    PropertyManagementFixtures::login($owner, $tenant);
    $this->get(route('billing.index'))->assertOk()->assertSee('Your subscription')->assertSee($invoice->number);
    $this->get(route('billing.invoices.show', $invoice->number))->assertOk()->assertSee('Property');

    PropertyManagementFixtures::login($manager, $tenant);
    $this->get(route('billing.index'))->assertForbidden();

    PropertyManagementFixtures::login($stranger, $strangerTenant);
    $this->get(route('billing.invoices.show', $invoice->number))->assertNotFound();
    $this->post(route('billing.subscribe'), ['modules' => [mod('crm')->id], 'interval' => 'weekly'])->assertSessionHasErrors('interval');
});
