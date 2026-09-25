<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Models\Interaction;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Marketing\Mail\MarketingMessage;
use App\Modules\Marketing\Models\Automation;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Marketing\Models\SavedCart;
use App\Modules\Marketing\Services\AutomationService;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Marketing\Support\SmsSender;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 22 (Marketing) — consent-gated email / SMS campaigns to segments and
| tags, personal single-use coupons redeemed on orders and stays,
| unsubscribe links, automations (abandoned booking / cart, review
| request, post-stay, reactivation), screens and permissions.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    Mail::fake();
    SmsSender::$sent = [];
    $this->travelTo(CarbonImmutable::parse('2030-09-05 10:00'));
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(2);
    foreach (['crm', 'restaurant'] as $slug) {
        app(ModuleService::class)->enableForTenant(Module::query()->where('slug', $slug)->firstOrFail(), $this->tenant);
    }
    MarketplaceFixtures::asTenant($this->tenant);

    $this->promo = Promotion::create(['code' => 'WELCOME', 'name' => 'Welcome back', 'type' => 'percent', 'value' => 10, 'applies_to' => Promotion::FOR_ORDERS]);
    $this->ana = Contact::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'marketing_consent' => true]);
    $this->ben = Contact::create(['name' => 'Ben Cruz', 'email' => 'ben@example.com']); // no consent
    $this->cora = Contact::create(['name' => 'Cora Lim', 'phone' => '09170000003', 'marketing_consent' => true, 'is_vip' => true]);
});

it('emails only opted-in guests, with personal coupons and an unsubscribe link, exactly once', function () {
    $campaign = Campaign::create(['name' => 'Rainy season', 'channel' => 'email', 'audience' => 'all', 'subject' => 'A treat for you', 'body' => 'Hi {name}! {coupon}', 'promotion_id' => $this->promo->id]);

    app(CampaignService::class)->send($campaign);

    Mail::assertSent(MarketingMessage::class, 1);
    Mail::assertSent(MarketingMessage::class, fn (MarketingMessage $m) => $m->hasTo('ana@example.com')
        && str_contains($m->text, 'Hi Ana!') && str_contains($m->text, 'Use code WELCOME-') && str_contains((string) $m->unsubscribeUrl, '/unsubscribe/'));

    $recipient = $campaign->recipients()->firstOrFail();
    expect($campaign->refresh()->status)->toBe(Campaign::SENT)->and($campaign->sent_count)->toBe(1)
        ->and(Coupon::query()->where('code', $recipient->coupon_code)->value('crm_contact_id'))->toBe($this->ana->id)
        ->and(Interaction::query()->where('crm_contact_id', $this->ana->id)->where('channel', 'email')->count())->toBe(1)
        ->and(fn () => app(CampaignService::class)->send($campaign))->toThrow(ValidationException::class);
});

it('targets segments and tags, and texts over SMS', function () {
    app(CrmService::class)->setTags($this->ana, ['Surfers']);
    $surfTag = $this->ana->tags()->first();

    $tagged = Campaign::create(['name' => 'Surf', 'channel' => 'email', 'audience' => 'tag:'.$surfTag->id, 'subject' => 'Waves', 'body' => 'Hi {name}']);
    expect(app(CampaignService::class)->audience($tagged)->pluck('name')->all())->toBe(['Ana Reyes']);

    $sms = Campaign::create(['name' => 'VIP night', 'channel' => 'sms', 'audience' => 'segment:vip', 'body' => 'Hi {name}, VIP dinner Friday at {business}.']);
    app(CampaignService::class)->send($sms);

    expect(SmsSender::$sent)->toHaveCount(1)
        ->and(SmsSender::$sent[0]['to'])->toBe('09170000003')
        ->and(SmsSender::$sent[0]['text'])->toContain('Hi Cora, VIP dinner Friday at Hotel A')->toContain('Reply STOP');
});

it('redeems a personal coupon once on a food order', function () {
    $restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'tax_rate' => 0, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Mains'])->id, 'name' => 'Sinigang', 'price' => 500]);
    $coupon = Coupon::create(['promotion_id' => $this->promo->id, 'crm_contact_id' => $this->ana->id, 'code' => 'WELCOME-ANA123']);

    $place = fn () => app(OrderService::class)->place($restaurant->refresh(), [['item_id' => $item->id, 'quantity' => 1]], ['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Ana', 'promo_code' => 'welcome-ana123'], null);

    $order = $place();
    expect((float) $order->discount_total)->toBe(50.0)
        ->and($coupon->refresh()->used_on)->toBe($order->reference)
        ->and($this->promo->refresh()->used_count)->toBe(1)
        ->and(fn () => $place())->toThrow(ValidationException::class);
});

it('lets a guest unsubscribe with the signed link only', function () {
    $url = URL::signedRoute('marketing.unsubscribe', ['tenant' => $this->tenant->id, 'contact' => $this->ana->id]);
    MarketplaceFixtures::asTenant(null);

    $this->get(str_replace('/unsubscribe/', '/unsubscribe/', $url).'x')->assertForbidden();
    $this->get($url)->assertOk()->assertSee('unsubscribed');

    MarketplaceFixtures::asTenant($this->tenant);
    expect($this->ana->refresh()->marketing_consent)->toBeFalse()->and($this->ana->consent_at)->toBeNull();
});

it('runs the automations once each, respecting consent', function () {
    $auto = app(AutomationService::class);
    $auto->all();
    Automation::query()->update(['enabled' => true]);
    Automation::query()->where('type', 'post_stay')->update(['promotion_id' => $this->promo->id]);

    // Abandoned booking: a marketplace request still pending after 2h (service message → no consent needed).
    $booker = User::factory()->create(['email' => 'booker@example.com']);
    $pending = BookingFixtures::reserve($this->property, $this->type, ['check_in' => '2030-10-01', 'check_out' => '2030-10-03'], Booking::SOURCE_MARKETPLACE, $booker);

    // A finished stay by an opted-in guest (review request + post-stay offer).
    $stayer = User::factory()->create(['email' => 'stayer@example.com']);
    $stay = BookingFixtures::reserve($this->property, $this->type, ['check_in' => '2030-09-05', 'check_out' => '2030-09-06', 'guest_email' => 'stayer@example.com'], Booking::SOURCE_MANUAL, $stayer);
    app(BookingService::class)->transition($stay, Booking::CHECKED_IN);
    $this->travelTo(CarbonImmutable::parse('2030-09-06 11:00'));
    app(BookingService::class)->transition($stay->refresh(), Booking::CHECKED_OUT);
    app(CrmService::class)->setConsent(app(CrmService::class)->upsert($stayer->id, 'Stayer', 'stayer@example.com', null, 'booking'), true);

    // An inactive opted-in guest.
    $this->ana->forceFill(['last_activity_at' => '2030-01-01'])->save();

    $this->travelTo(CarbonImmutable::parse('2030-09-14 12:00')); // > 168h after check-out
    $first = $auto->run();
    $second = $auto->run(); // nothing twice

    expect($first)->toMatchArray(['abandoned_booking' => 1, 'review_request' => 1, 'post_stay' => 1, 'reactivation' => 1])
        ->and(array_sum($second))->toBe(0);

    Mail::assertSent(MarketingMessage::class, fn ($m) => $m->hasTo('booker@example.com') && $m->unsubscribeUrl === null && str_contains($m->text, $pending->reference));
    Mail::assertSent(MarketingMessage::class, fn ($m) => $m->hasTo('stayer@example.com') && str_contains($m->text, 'Use code WELCOME-'));
    Mail::assertSent(MarketingMessage::class, fn ($m) => $m->hasTo('ana@example.com') && $m->subjectLine === 'We miss you');
});

it('reminds opted-in guests about carts they left, and forgets carts that checked out', function () {
    $restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'tax_rate' => 0, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Mains'])->id, 'name' => 'Lechon', 'price' => 600]);
    $guest = User::factory()->create(['email' => 'hungry@example.com']);
    $auto = app(AutomationService::class);
    $auto->all();
    Automation::query()->where('type', 'abandoned_cart')->update(['enabled' => true]);

    MarketplaceFixtures::asTenant(null);
    $this->actingAs($guest)->post(route('cart.add', $restaurant->slug), ['item_id' => $item->id, 'quantity' => 1])->assertSessionHasNoErrors();
    MarketplaceFixtures::asTenant($this->tenant);
    expect(SavedCart::query()->count())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2030-09-05 13:00'));
    expect($auto->run()['abandoned_cart'])->toBe(0); // no consent yet

    app(CrmService::class)->setConsent(app(CrmService::class)->upsert($guest->id, $guest->name, $guest->email, null, 'order'), true);
    expect($auto->run()['abandoned_cart'])->toBe(1);
    Mail::assertSent(MarketingMessage::class, fn ($m) => $m->hasTo('hungry@example.com') && str_contains($m->text, '/restaurant/'));

    MarketplaceFixtures::asTenant(null);
    $this->post(route('cart.checkout'), ['fulfillment' => 'pickup', 'payment_method' => 'cash', 'customer_phone' => '0917'])->assertRedirect();
    MarketplaceFixtures::asTenant($this->tenant);
    expect(SavedCart::query()->count())->toBe(0);
});

it('runs the marketing screens with permissions and gating', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->get(route('marketing.index'))->assertOk()->assertSee('Abandoned cart')->assertSee('WELCOME');
    $this->post(route('marketing.campaigns.store'), ['name' => 'Fiesta', 'channel' => 'email', 'audience' => 'segment:vip', 'subject' => 'Fiesta', 'body' => 'Hi {name}'])->assertRedirect();
    $campaign = Campaign::query()->firstOrFail();
    $this->get(route('marketing.campaigns.show', $campaign->id))->assertOk()->assertSee('0 opted-in guest(s) reachable'); // Cora is VIP but has no email
    $this->post(route('marketing.campaigns.schedule', $campaign->id), ['scheduled_at' => '2030-09-06 09:00'])->assertSessionHasNoErrors();
    $this->patch(route('marketing.automations.update', 'review_request'), ['enabled' => 1, 'delay_hours' => 12, 'subject' => 'Rate us', 'body' => 'Hi {name} {link}'])->assertSessionHasNoErrors();
    expect(Automation::query()->where('type', 'review_request')->value('enabled'))->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2030-09-06 09:05'));
    expect(app(CampaignService::class)->runScheduled())->toBe(1);

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'front_desk'), $this->tenant);
    $this->get(route('marketing.index'))->assertForbidden();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('marketing.index'))->assertForbidden();
});
