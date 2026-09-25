<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Folio\Models\FolioEntry;
use App\Modules\Folio\Services\FolioService;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PayMongoFake;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 14 (Guest Folio) — room nights, discount, room-service charges,
| manual charges, desk payments / refunds, online payments, voids,
| early check-out, cancellation, screens and isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    PayMongoFake::configure();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 15:00')); // Thursday

    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel();
    $this->guest = User::factory()->create();
    // Thu ₱3,500 + Fri ₱4,500 + Sat ₱4,500 (weekend rate).
    $this->stay = BookingFixtures::reserve($this->property, $this->type, [], Booking::SOURCE_MANUAL, $this->guest);
    MarketplaceFixtures::asTenant($this->tenant);
});

function folio(): FolioService
{
    return app(FolioService::class);
}

function totalsOf(Booking $booking): array
{
    folio()->sync($booking->refresh());

    return folio()->totals($booking);
}

it('posts every room night once, however often it syncs', function () {
    folio()->sync($this->stay);
    folio()->sync($this->stay);

    $nights = FolioEntry::query()->where('booking_id', $this->stay->id)->where('category', 'room')->get();
    expect($nights)->toHaveCount(3)
        ->and($nights->pluck('amount')->map(fn ($a) => (float) $a)->all())->toBe([3500.0, 4500.0, 4500.0])
        ->and(totalsOf($this->stay))->toMatchArray(['charges' => 12500.0, 'payments' => 0.0, 'balance' => 12500.0]);
});

it('adds manual charges, desk payments and refunds, and voids mistakes', function () {
    $desk = $this->owner;
    folio()->addCharge($this->stay, 'transport', 'Airport transfer', 800, 1, $desk);
    $minibar = folio()->addCharge($this->stay, 'minibar', 'Beer', 120, 3, $desk);
    folio()->addCharge($this->stay, 'food', 'Breakfast', 850, 1, $desk);

    expect(totalsOf($this->stay))->toMatchArray(['charges' => 14510.0, 'balance' => 14510.0])
        ->and(totalsOf($this->stay)['by_category'])->toMatchArray(['room' => 12500.0, 'transport' => 800.0, 'minibar' => 360.0, 'food' => 850.0]);

    folio()->void($minibar, 'Posted to wrong room', $desk);
    folio()->recordPayment($this->stay, 'cash', 10000, $desk);
    folio()->recordPayment($this->stay, 'card', 4150, $desk, 'AUTH 123');

    expect(totalsOf($this->stay))->toMatchArray(['charges' => 14150.0, 'payments' => 14150.0, 'balance' => 0.0]);

    folio()->recordRefund($this->stay, 'cash', 500, $desk, 'Goodwill');
    expect(totalsOf($this->stay)['balance'])->toBe(500.0)
        ->and(fn () => folio()->recordRefund($this->stay, 'cash', 20000, $desk))->toThrow(ValidationException::class)
        ->and(fn () => folio()->addCharge($this->stay, 'room', 'Sneaky night', 1, 1, $desk))->toThrow(ValidationException::class)
        ->and(fn () => folio()->void($minibar->refresh(), 'again', $desk))->toThrow(ValidationException::class);

    // Synced lines follow their source records instead.
    $night = FolioEntry::query()->where('booking_id', $this->stay->id)->where('category', 'room')->first();
    expect(fn () => folio()->void($night, 'no', $desk))->toThrow(ValidationException::class);
});

it('charges only the nights stayed after an early check-out', function () {
    $bookings = app(BookingService::class);
    $bookings->transition($this->stay, Booking::CHECKED_IN);
    folio()->sync($this->stay);

    $this->travelTo(CarbonImmutable::parse('2030-09-06 10:00'));
    $bookings->transition($this->stay->refresh(), Booking::CHECKED_OUT);

    expect(totalsOf($this->stay)['charges'])->toBe(3500.0)
        ->and(FolioEntry::query()->where('booking_id', $this->stay->id)->whereNotNull('voided_at')->count())->toBe(2);
});

it('drops room charges when the booking is cancelled before the stay', function () {
    folio()->sync($this->stay);
    app(BookingService::class)->transition($this->stay, Booking::CANCELLED, 'Plans changed');

    expect(totalsOf($this->stay)['charges'])->toBe(0.0);
});

it('carries room-service orders charged to the room and voids cancelled ones', function () {
    app(BookingService::class)->transition($this->stay, Booking::CHECKED_IN);
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $this->tenant);
    $restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'room_service_enabled' => true, 'tax_rate' => 12, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Room dining'])->id, 'name' => 'Pancit', 'price' => 425]);

    $orders = app(OrderService::class);
    $place = fn () => $orders->place($restaurant->refresh(), [['item_id' => $item->id, 'quantity' => 2]], [
        'fulfillment' => Order::ROOM_SERVICE, 'payment_method' => Order::PAY_ROOM, 'customer_name' => 'Guest', 'booking_id' => $this->stay->id,
    ], $this->guest);

    $kept = $place();
    $cancelled = $place();
    expect(totalsOf($this->stay)['by_category']['room_service'])->toBe(1700.0);

    $orders->transition($cancelled, Order::CANCELLED);
    expect(totalsOf($this->stay)['by_category']['room_service'])->toBe(850.0)
        ->and(totalsOf($this->stay)['charges'])->toBe(13350.0);
});

it('counts online payments and refunds from PayMongo', function () {
    [$guest, $booking] = PayMongoFake::paidBooking();
    MarketplaceFixtures::asListing($booking);

    expect(totalsOf($booking))->toMatchArray(['charges' => 12500.0, 'payments' => 12500.0, 'balance' => 0.0]);

    app(BookingService::class)->transition($booking->refresh(), Booking::CANCELLED);
    app(BookingService::class)->transition($booking->refresh(), Booking::REFUNDED);

    expect(totalsOf($booking))->toMatchArray(['charges' => 0.0, 'payments' => 12500.0, 'refunds' => 12500.0, 'balance' => 0.0]);
});

it('runs the folio screens with permissions and isolation', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->get(route('bookings.show', $this->stay->reference))->assertOk()->assertSee('Folio');
    $this->get(route('folio.show', $this->stay->reference))->assertOk()->assertSee('₱12,500.00');
    $this->post(route('folio.charges.store', $this->stay->reference), ['category' => 'laundry', 'description' => 'Laundry bag', 'unit_amount' => 350])->assertSessionHasNoErrors();
    $this->post(route('folio.payments.store', $this->stay->reference), ['method' => 'cash', 'amount' => 5000])->assertSessionHasNoErrors();
    $this->get(route('folio.print', $this->stay->reference))->assertOk()->assertSee('Laundry bag')->assertSee('₱7,850.00');

    $desk = MarketplaceFixtures::member($this->tenant, 'front_desk');
    PropertyManagementFixtures::login($desk, $this->tenant);
    $laundry = FolioEntry::query()->where('description', 'Laundry bag')->firstOrFail();
    $this->post(route('folio.charges.store', $this->stay->reference), ['category' => 'minibar', 'description' => 'Water', 'unit_amount' => 60])->assertSessionHasNoErrors();
    $this->post(route('folio.void', [$this->stay->reference, $laundry->id]), ['reason' => 'x'])->assertForbidden();

    // The guest sees their own folio; nobody else does.
    MarketplaceFixtures::asTenant(null);
    $this->actingAs($this->guest)->get(route('account.folio', $this->stay->reference))->assertOk()->assertSee('Laundry bag');
    $this->actingAs(User::factory()->create())->get(route('account.folio', $this->stay->reference))->assertNotFound();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    BookingFixtures::enableModule($tenantB);
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('folio.show', $this->stay->reference))->assertNotFound();
    $this->post(route('folio.payments.store', $this->stay->reference), ['method' => 'cash', 'amount' => 1])->assertNotFound();
});
