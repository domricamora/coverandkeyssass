<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Models\Interaction;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Crm\Support\Segments;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Modules\RestaurantManagement\Services\ReservationService;
use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 21 (CRM) — contacts folded from bookings / orders / reservations,
| cached metrics, the seven segments, tags, notes, consent, communication
| history, profile screens, permissions and isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 12:00'));
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(3);
    foreach (['crm', 'restaurant'] as $slug) {
        app(ModuleService::class)->enableForTenant(Module::query()->where('slug', $slug)->firstOrFail(), $this->tenant);
    }
    MarketplaceFixtures::asTenant($this->tenant);
    $this->guest = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);
});

function stay(array $attributes = [], ?User $customer = null, bool $checkOut = true): Booking
{
    $booking = BookingFixtures::reserve(test()->property, test()->type, $attributes + ['guest_email' => 'maria@example.com', 'guest_phone' => '0917 000 0000'], Booking::SOURCE_MANUAL, $customer);
    $bookings = app(BookingService::class);

    if ($checkOut) {
        test()->travelTo(CarbonImmutable::parse($booking->check_in->toDateString().' 14:00'));
        $bookings->transition($booking, Booking::CHECKED_IN);
        test()->travelTo(CarbonImmutable::parse($booking->check_out->toDateString().' 11:00'));
        $bookings->transition($booking->refresh(), Booking::CHECKED_OUT);
    }

    return $booking->refresh();
}

function crm(): CrmService
{
    return app(CrmService::class);
}

it('folds bookings, orders and table reservations into one guest', function () {
    stay([], $this->guest);                                          // account + email + phone
    stay(['check_in' => '2030-09-10', 'check_out' => '2030-09-12']); // same email, no account

    $restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'reservations_enabled' => true, 'tax_rate' => 12, 'tax_inclusive' => true, 'opening_hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '11:00–22:00')]);
    MarketplaceFixtures::asTenant($this->tenant);
    $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Mains'])->id, 'name' => 'Adobo', 'price' => 400]);
    RestaurantTable::create(['restaurant_id' => $restaurant->id, 'label' => 'T1', 'seats' => 4, 'status' => 'active']);

    $orders = app(OrderService::class);
    $order = $orders->place($restaurant->refresh(), [['item_id' => $item->id, 'quantity' => 2]], ['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Maria', 'customer_phone' => '0917 000 0000'], $this->guest);
    foreach ([Order::ACCEPTED, Order::PREPARING, Order::READY, Order::COMPLETED] as $state) {
        $orders->transition($order, $state);
    }

    $table = app(ReservationService::class)->reserve($restaurant, ['date' => '2030-09-13', 'time' => '19:00', 'party_size' => 2, 'guest_name' => 'Maria', 'guest_email' => 'MARIA@example.com'], TableReservation::SOURCE_HOST);
    $this->travelTo(CarbonImmutable::parse('2030-09-13 19:05'));
    app(ReservationService::class)->transition($table, TableReservation::SEATED);

    // A walk-in with no contact details stays anonymous.
    $orders->place($restaurant, [['item_id' => $item->id, 'quantity' => 1]], ['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Walk-in'], null);

    crm()->sync();
    crm()->sync(); // idempotent

    expect(Contact::query()->count())->toBe(1);
    $maria = Contact::query()->firstOrFail();
    expect($maria->user_id)->toBe($this->guest->id)
        ->and($maria->bookings_count)->toBe(2)
        ->and($maria->orders_count)->toBe(1)
        ->and($maria->reservations_count)->toBe(1)
        ->and((float) $maria->total_spend)->toBe(12500.0 + 7000.0 + 800.0) // two stays + one order
        ->and($maria->last_activity_at->toDateString())->toBe('2030-09-13');
});

it('places guests in the seven segments', function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-01 12:00'));
    $regular = User::factory()->create();
    foreach (['2030-01-10', '2030-02-10', '2030-03-10'] as $in) {
        stay(['check_in' => $in, 'check_out' => CarbonImmutable::parse($in)->addDays(2)->toDateString(), 'guest_email' => 'reg@example.com', 'guest_phone' => '0918 111 1111'], $regular);
    }
    $this->travelTo(CarbonImmutable::parse('2030-03-20 12:00'));
    stay(['check_in' => '2030-03-20', 'check_out' => '2030-03-21', 'guest_email' => 'newbie@example.com', 'guest_phone' => '0918 222 2222'], User::factory()->create());

    crm()->sync();
    $vip = Contact::create(['name' => 'Mayor', 'email' => 'mayor@example.com', 'is_vip' => true]);

    $in = fn (string $segment) => Segments::apply(Contact::query(), $segment)->pluck('email')->sort()->values()->all();

    expect($in('vip'))->toBe(['mayor@example.com'])
        ->and($in('frequent_guest'))->toBe(['reg@example.com'])
        ->and($in('high_spender'))->toBe(['reg@example.com'])
        ->and($in('hotel_customer'))->toBe(['newbie@example.com', 'reg@example.com'])
        ->and($in('restaurant_customer'))->toBe([])
        ->and($in('new_customer'))->toContain('newbie@example.com')->not->toContain('reg@example.com');

    $this->travelTo(CarbonImmutable::parse('2030-10-15'));
    expect($in('inactive'))->toBe(['newbie@example.com', 'reg@example.com']);
});

it('keeps tags, notes, consent and a de-duplicated communication log', function () {
    $contact = Contact::create(['name' => 'Ana Reyes', 'email' => 'ana@example.com']);

    crm()->setTags($contact, ['Honeymoon', ' honeymoon ', 'Vegan', '']);
    expect($contact->tags()->pluck('name')->all())->toBe(['Honeymoon', 'Vegan']);
    crm()->setTags($contact, ['Vegan']);
    expect($contact->tags()->count())->toBe(1);

    crm()->setConsent($contact, true);
    expect($contact->refresh()->marketing_consent)->toBeTrue()->and($contact->consent_at)->not->toBeNull();

    crm()->log($contact, 'email', 'outbound', 'Welcome back', 'Hi Ana', null, 'campaign:1:contact:'.$contact->id);
    crm()->log($contact, 'email', 'outbound', 'Welcome back', 'Hi Ana', null, 'campaign:1:contact:'.$contact->id); // retry
    crm()->log($contact, 'phone', 'inbound', 'Asked about late checkout', null, $this->owner);
    crm()->note($contact, 'Allergic to shellfish.', $this->owner);

    expect(Interaction::query()->count())->toBe(2)->and($contact->notes()->count())->toBe(1);
});

it('runs the guest screens with permissions, gating and isolation', function () {
    $booking = stay([], $this->guest);
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->get(route('crm.index'))->assertOk()->assertSee('maria@example.com')->assertSee('Hotel Customer');
    $contact = Contact::query()->firstOrFail();

    $this->patch(route('crm.update', $contact->id), ['name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '0917 000 0000', 'tags' => 'VIP-friend, Repeat', 'is_vip' => 1, 'marketing_consent' => 1])->assertSessionHasNoErrors();
    $this->post(route('crm.notes.store', $contact->id), ['body' => 'Prefers a sea view.'])->assertSessionHasNoErrors();
    $this->post(route('crm.interactions.store', $contact->id), ['channel' => 'phone', 'direction' => 'inbound', 'subject' => 'Called about rates'])->assertSessionHasNoErrors();

    $this->get(route('crm.show', $contact->id))->assertOk()->assertSee('Prefers a sea view.')->assertSee('Called about rates')->assertSee('Repeat')->assertSee($booking->reference);
    $this->get(route('crm.index', ['segment' => 'vip']))->assertOk()->assertSee('Maria Santos');
    $this->post(route('crm.store'), ['name' => 'Dup', 'email' => 'MARIA@example.com'])->assertSessionHasErrors('email');

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'staff'), $this->tenant);
    $this->get(route('crm.index'))->assertForbidden();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('crm.index'))->assertForbidden(); // crm module not active
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'crm')->firstOrFail(), $tenantB);
    $this->get(route('crm.index'))->assertOk()->assertDontSee('maria@example.com');
    $this->get(route('crm.show', $contact->id))->assertNotFound();
});
