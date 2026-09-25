<?php

use App\Models\AuditLog;
use App\Models\ModulePlan;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Payments\Models\Payment;
use App\Modules\PlatformAdmin\Models\Page;
use App\Modules\Wallet\Models\CommissionRate;
use Illuminate\Support\Facades\Http;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PayMongoFake;

/*
| Phase 28 (Super Admin) — platform dashboard, listing control, bookings /
| orders / payments with refunds, pricing, CMS pages, settings, CSV reports,
| audit log and user filters; all behind the Super Admin gate.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    PayMongoFake::configure();
    $this->admin = User::factory()->create(['name' => 'Platform Admin']);
    $this->admin->assignPlatformRole();
});

it('shows the platform dashboard and admin screens to Super Admins only', function () {
    [$guest, $booking] = PayMongoFake::paidBooking();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Guest payments')->assertSee('₱12,500.00')->assertSee('Platform commission')->assertSee('Subscriptions');

    foreach (['admin.bookings.index', 'admin.orders.index', 'admin.payments.index', 'admin.pricing.index', 'admin.settings.index', 'admin.pages.index', 'admin.reports.index', 'admin.logs.index'] as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get(route('admin.bookings.index', ['q' => $booking->reference]))->assertSee($booking->guest_name);
    $this->get(route('admin.payments.index', ['status' => 'paid']))->assertSee('Booking '.$booking->reference);

    $this->actingAs($guest)->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('admin.payments.index'))->assertForbidden();
    $this->post(route('admin.payments.refund', 1), ['reason' => 'x'])->assertForbidden();
});

it('approves, suspends and reinstates listings with an audited reason', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Hotel A');
    $property = MarketplaceFixtures::property($tenant, $owner, ['status' => Property::STATUS_PENDING, 'name' => 'Seaside Inn']);
    MarketplaceFixtures::asTenant(null);

    $this->actingAs($this->admin)->get(route('admin.listings.index', 'properties'))->assertOk()->assertSee('Seaside Inn')->assertSee('Approve');

    $this->post(route('admin.listings.status', ['properties', $property->id]), ['status' => 'published'])->assertRedirect();
    expect(Property::query()->withoutGlobalScope('tenant')->find($property->id)->status)->toBe('published');

    $this->post(route('admin.listings.status', ['properties', $property->id]), ['status' => 'suspended'])->assertSessionHasErrors('reason');
    $this->post(route('admin.listings.status', ['properties', $property->id]), ['status' => 'suspended', 'reason' => 'Fake photos'])->assertRedirect();

    expect(Property::query()->withoutGlobalScope('tenant')->find($property->id)->status)->toBe('suspended')
        ->and(AuditLog::query()->where('action', 'platform.listing.suspended')->sole()->new_values['reason'])->toBe('Fake photos');

    $this->get(route('admin.listings.index', 'hotels'))->assertNotFound();
});

it('refunds a paid booking by cancelling it first, through PayMongo', function () {
    [$guest, $booking] = PayMongoFake::paidBooking();
    $payment = Payment::forCustomer($guest)->sole();
    expect($booking->refresh()->status)->toBe(Booking::CONFIRMED);

    $this->actingAs($this->admin)->post(route('admin.payments.refund', $payment->id), ['reason' => 'Double charge'])->assertRedirect()->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::REFUNDED)
        ->and($payment->refresh()->status)->toBe(Payment::REFUNDED);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refunds'));

    // Not paid any more.
    $this->post(route('admin.payments.refund', $payment->id), ['reason' => 'Again'])->assertSessionHasErrors('refund');
});

it('edits module pricing and platform settings that the platform then uses', function () {
    $plan = ModulePlan::query()->whereHas('module', fn ($q) => $q->where('slug', 'crm'))->where('billing_interval', 'monthly')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.pricing.update'), ['plans' => [$plan->id => ['price' => '499.00', 'is_active' => '1']]])->assertRedirect();
    expect($plan->refresh()->price_cents)->toBe(49900)
        ->and(AuditLog::query()->where('action', 'platform.pricing.updated')->exists())->toBeTrue();

    $this->put(route('admin.settings.update'), ['support_email' => 'not-an-email'])->assertSessionHasErrors('support_email');
    $this->put(route('admin.settings.update'), [
        'support_email' => 'help@coverandkeys.test',
        'announcement' => 'Rainy-season sale: 20% off stays',
        'commission_default_rate' => '12.5',
    ])->assertRedirect();

    expect(CommissionRate::defaultRate())->toBe(12.5);
    $this->get(route('marketplace.hotels'))->assertOk()->assertSee('Rainy-season sale: 20% off stays')->assertSee('help@coverandkeys.test');
});

it('publishes CMS pages as safe Markdown, with drafts hidden from the public', function () {
    $this->actingAs($this->admin)->post(route('admin.pages.store'), [
        'title' => 'Terms of Service', 'slug' => 'terms', 'body' => "# Terms\n\nBe **nice**.\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
        'is_published' => '1', 'in_footer' => '1',
    ])->assertRedirect(route('admin.pages.index'));
    $this->post(route('admin.pages.store'), ['title' => 'Draft', 'slug' => 'draft-page', 'body' => 'Soon'])->assertRedirect();
    $this->post(route('admin.pages.store'), ['title' => 'Dup', 'slug' => 'terms', 'body' => 'x'])->assertSessionHasErrors('slug');

    $this->get(route('pages.show', 'draft-page'))->assertOk()->assertSee('Draft preview');

    auth()->logout();
    $this->get(route('pages.show', 'terms'))->assertOk()
        ->assertSee('<strong>nice</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('javascript:alert', false);
    $this->get(route('pages.show', 'draft-page'))->assertNotFound();
    $this->get(route('marketplace.hotels'))->assertSee(route('pages.show', 'terms'));

    $page = Page::query()->where('slug', 'terms')->sole();
    $this->actingAs($this->admin)->put(route('admin.pages.update', $page), ['title' => 'Terms', 'slug' => 'terms', 'body' => 'Updated'])->assertRedirect();
    expect($page->refresh())->is_published->toBeFalse()->body->toBe('Updated');
});

it('exports CSV reports safely, filters the audit log and splits hosts from customers', function () {
    [$guest, $booking] = PayMongoFake::paidBooking();
    Booking::query()->withoutGlobalScope('tenant')->whereKey($booking->id)->update(['guest_name' => '=HYPERLINK("http://evil")']);

    $response = $this->actingAs($this->admin)->get(route('admin.reports.export', ['bookings', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]));
    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('Reference,Business,Status')
        ->and($csv)->toContain($booking->reference)
        ->and($csv)->toContain("'=HYPERLINK");

    $this->get(route('admin.reports.export', ['bookings', 'from' => '2026-02-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
    $this->get(route('admin.reports.export', ['nope', 'from' => '2026-01-01', 'to' => '2026-01-02']))->assertNotFound();

    $this->get(route('admin.logs.index', ['action' => 'payment']))->assertOk()->assertSee('payment.paid')->assertDontSee('booking.confirmed');

    [$owner] = MarketplaceFixtures::business('Hotel Z');
    $this->get(route('admin.users.index', ['type' => 'hosts']))->assertSee($owner->email)->assertDontSee($guest->email);
    $this->get(route('admin.users.index', ['type' => 'customers']))->assertSee($guest->email)->assertDontSee($owner->email)
        ->assertViewHas('users', fn ($users) => ! $users->contains('id', $this->admin->id));
});
