<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Modules\RestaurantManagement\Services\ReservationService;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 10 (Restaurant Reservations) — time slots, table allocation without
| overbooking, the state machine, host desk, marketplace request and the
| customer's own reservations.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    PropertyManagementFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-06-03 09:00')); // a Monday
});

/** @return array{0: User, 1: Tenant, 2: Restaurant} */
function diningRoom(array $attributes = []): array
{
    [$owner, $tenant] = MarketplaceFixtures::business('Kitchen A');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenant);

    $hours = array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], '11:00–14:00, 17:00–22:00');
    $restaurant = MarketplaceFixtures::restaurant($tenant, $owner, $attributes + [
        'status' => Restaurant::STATUS_PUBLISHED,
        'reservations_enabled' => true,
        'opening_hours' => $hours + ['sunday' => 'Closed'],
    ]);

    MarketplaceFixtures::asListing($restaurant);
    foreach (['T2' => 2, 'T4' => 4] as $label => $seats) {
        RestaurantTable::create(['restaurant_id' => $restaurant->id, 'label' => $label, 'seats' => $seats]);
    }

    return [$owner, $tenant, $restaurant->refresh()];
}

function reserveTable(Restaurant $restaurant, string $time, int $party = 2, string $date = '2030-06-04'): TableReservation
{
    return app(ReservationService::class)->reserve($restaurant, [
        'date' => $date, 'time' => $time, 'party_size' => $party, 'guest_name' => 'Walk Up',
    ], TableReservation::SOURCE_HOST);
}

it('derives time slots from opening hours and the sitting length', function () {
    [, , $restaurant] = diningRoom();
    $service = app(ReservationService::class);

    $slots = $service->slotsFor($restaurant, CarbonImmutable::parse('2030-06-04'));

    expect($slots[0])->toBe('11:00')
        ->and($slots)->toContain('12:30')->not->toContain('13:00') // 90-minute sitting before 14:00
        ->and($slots)->toContain('17:00')->toContain('20:30')->not->toContain('21:00')
        ->and($service->slotsFor($restaurant, CarbonImmutable::parse('2030-06-09')))->toBe([]); // Sunday: Closed
});

it('never double-books a table and picks the smallest one that fits', function () {
    [, , $restaurant] = diningRoom();

    $first = reserveTable($restaurant, '19:00');
    $second = reserveTable($restaurant, '19:00');

    expect($first->table->label)->toBe('T2')->and($second->table->label)->toBe('T4');

    // Both tables are held until 20:30.
    expect(fn () => reserveTable($restaurant, '19:00'))->toThrow(ValidationException::class)
        ->and(fn () => reserveTable($restaurant, '20:00'))->toThrow(ValidationException::class);

    // Back-to-back after the sitting ends is fine.
    expect(reserveTable($restaurant, '20:30')->table->label)->toBe('T2');

    // A party of 4 only fits T4; cancelling frees it.
    expect(fn () => reserveTable($restaurant, '19:30', 4))->toThrow(ValidationException::class);
    app(ReservationService::class)->transition($second, TableReservation::CANCELLED);
    expect(reserveTable($restaurant, '19:30', 4)->table->label)->toBe('T4');

    // Bigger than the largest table.
    expect(fn () => reserveTable($restaurant, '12:00', 9))->toThrow(ValidationException::class);

    $overlaps = TableReservation::query()->blocking()->get()->groupBy('restaurant_table_id')
        ->flatMap(fn ($rows) => $rows->filter(fn ($a) => $rows->contains(fn ($b) => $a->isNot($b) && $a->reserved_at < $b->ends_at && $a->ends_at > $b->reserved_at)));
    expect($overlaps)->toBeEmpty();
});

it('walks the reservation state machine with date guards', function () {
    [, , $restaurant] = diningRoom();
    $service = app(ReservationService::class);
    $r = reserveTable($restaurant, '19:00');

    expect($r->status)->toBe(TableReservation::CONFIRMED)->and($r->confirmed_at)->not->toBeNull();

    // Seating only on the day; no-show only after the time.
    expect(fn () => $service->transition($r, TableReservation::SEATED))->toThrow(ValidationException::class)
        ->and(fn () => $service->transition($r, TableReservation::NO_SHOW))->toThrow(ValidationException::class)
        ->and(fn () => $service->transition($r, TableReservation::COMPLETED))->toThrow(ValidationException::class);

    $this->travelTo(CarbonImmutable::parse('2030-06-04 19:05'));
    $service->transition($r, TableReservation::SEATED);

    $this->travelTo(CarbonImmutable::parse('2030-06-04 19:50'));
    $service->transition($r, TableReservation::COMPLETED);

    // Leaving early frees the table for the rest of the sitting.
    expect($r->refresh()->ends_at->format('H:i'))->toBe('19:50')
        ->and(reserveTable($restaurant, '20:00', 2, '2030-06-04')->table->label)->toBe('T2');

    $late = reserveTable($restaurant, '21:00', 2, '2030-06-04');
    $this->travelTo(CarbonImmutable::parse('2030-06-04 21:30'));
    $service->transition($late, TableReservation::NO_SHOW);
    expect($late->refresh()->status)->toBe(TableReservation::NO_SHOW);
});

it('takes marketplace requests only for open slots at enabled restaurants', function () {
    [, $tenant, $restaurant] = diningRoom();
    $guest = User::factory()->create();
    MarketplaceFixtures::asTenant(null);

    $this->post(route('marketplace.restaurants.reserve', $restaurant->slug), ['date' => '2030-06-04', 'time' => '19:00', 'party_size' => 2])
        ->assertRedirect(route('login'));

    $this->actingAs($guest);

    $this->getJson(route('marketplace.restaurants.slots', [$restaurant->slug, 'date' => '2030-06-04']))
        ->assertOk()->assertJsonFragment(['date' => '2030-06-04'])->assertJsonPath('slots.0', '11:00');

    // Outside the slot grid.
    $this->post(route('marketplace.restaurants.reserve', $restaurant->slug), ['date' => '2030-06-04', 'time' => '15:00', 'party_size' => 2])
        ->assertSessionHasErrors('time');

    $this->post(route('marketplace.restaurants.reserve', $restaurant->slug), [
        'date' => '2030-06-04', 'time' => '19:00', 'party_size' => 3, 'special_requests' => 'Window seat',
    ])->assertRedirect(route('account.reservations.index'));

    $reservation = TableReservation::forCustomer($guest)->firstOrFail();
    expect($reservation->status)->toBe(TableReservation::PENDING)
        ->and($reservation->tenant_id)->toBe($tenant->id)
        ->and($reservation->special_requests)->toBe('Window seat');

    $this->get(route('marketplace.restaurants.show', $restaurant->slug))->assertSee('Book a table');

    // Reservations switched off → the request endpoint is a 404.
    MarketplaceFixtures::asListing($restaurant);
    $restaurant->update(['reservations_enabled' => false]);
    MarketplaceFixtures::asTenant(null);
    $this->post(route('marketplace.restaurants.reserve', $restaurant->slug), ['date' => '2030-06-04', 'time' => '19:00', 'party_size' => 2])
        ->assertNotFound();
    $this->get(route('marketplace.restaurants.show', $restaurant->slug))->assertDontSee('Book a table');
});

it('lets customers see and cancel only their own reservations', function () {
    [, $tenant, $restaurant] = diningRoom();
    $guest = User::factory()->create();
    $other = User::factory()->create();

    $mine = app(ReservationService::class)->reserve($restaurant, [
        'date' => '2030-06-04', 'time' => '19:00', 'party_size' => 2, 'guest_name' => $guest->name,
    ], TableReservation::SOURCE_MARKETPLACE, $guest, $guest);

    // Host confirms → the guest is notified.
    app(ReservationService::class)->transition($mine, TableReservation::CONFIRMED);
    expect($guest->notifications()->count())->toBe(1);

    MarketplaceFixtures::asTenant(null);

    $this->actingAs($other)->get(route('account.reservations.index'))->assertOk()->assertDontSee($mine->reference);
    $this->post(route('account.reservations.cancel', $mine->reference))->assertNotFound();

    $this->actingAs($guest)->get(route('account.reservations.index'))->assertOk()->assertSee($mine->reference);
    $this->post(route('account.reservations.cancel', $mine->reference))->assertSessionHasNoErrors();

    expect(TableReservation::forCustomer($guest)->first()->status)->toBe(TableReservation::CANCELLED);
});

it('runs the host reservation desk with permissions and tenant isolation', function () {
    [$owner, $tenant, $restaurant] = diningRoom();
    PropertyManagementFixtures::login($owner, $tenant);

    $this->post(route('restaurants.reservations.store', $restaurant), [
        'date' => '2030-06-04', 'time' => '18:00', 'party_size' => 2, 'guest_name' => 'Phone Guest',
        'restaurant_table_id' => $restaurant->tables()->where('label', 'T4')->value('id'),
    ])->assertSessionHasNoErrors();

    $r = TableReservation::query()->firstOrFail();
    expect($r->table->label)->toBe('T4')->and($r->status)->toBe(TableReservation::CONFIRMED);

    $this->get(route('restaurants.reservations', [$restaurant, 'date' => '2030-06-04']))
        ->assertOk()->assertSee('Phone Guest')->assertSee('T4');

    $this->post(route('restaurants.reservations.transition', [$restaurant, $r->reference]), ['status' => 'cancelled'])
        ->assertSessionHasNoErrors();
    expect($r->refresh()->status)->toBe(TableReservation::CANCELLED);

    // Front desk can run the desk; plain staff cannot.
    $desk = MarketplaceFixtures::member($tenant, 'front_desk');
    PropertyManagementFixtures::login($desk, $tenant);
    $this->get(route('restaurants.reservations', $restaurant))->assertOk();

    $staff = MarketplaceFixtures::member($tenant, 'staff');
    PropertyManagementFixtures::login($staff, $tenant);
    $this->get(route('restaurants.reservations', $restaurant))->assertForbidden();

    // Another business cannot reach this restaurant's reservations.
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Kitchen B');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenantB);
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('restaurants.reservations', $restaurant))->assertNotFound();
    $this->post(route('restaurants.reservations.transition', [$restaurant, $r->reference]), ['status' => 'confirmed'])->assertNotFound();
});
