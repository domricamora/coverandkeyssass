<?php

use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Models\Review;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Modules\RestaurantManagement\Services\ReservationService;
use App\Modules\Reviews\Services\ReviewService;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 24 (Reviews) — verified stay / order / table-visit reviews with
| category, room and dish ratings, aggregates on the listing pages,
| host replies and reports, Super Admin moderation, permissions.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 12:00'));
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(2);
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $this->tenant);
    MarketplaceFixtures::asTenant($this->tenant);
    $this->property->publish();

    $this->restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'reservations_enabled' => true, 'tax_rate' => 0, 'tax_inclusive' => true, 'opening_hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '11:00–22:00')]);
    MarketplaceFixtures::asTenant($this->tenant);
    $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Mains']);
    $this->adobo = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Adobo', 'price' => 300]);
    $this->halo = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Halo-halo', 'price' => 150]);
    $this->guest = User::factory()->create(['name' => 'Maria']);
});

function reviews(): ReviewService
{
    return app(ReviewService::class);
}

function finishedStay(User $guest, string $in = '2030-09-05', string $out = '2030-09-07'): Booking
{
    $booking = BookingFixtures::reserve(test()->property, test()->type, ['check_in' => $in, 'check_out' => $out], Booking::SOURCE_MARKETPLACE, $guest);
    $svc = app(BookingService::class);
    test()->travelTo(CarbonImmutable::parse($in.' 14:00'));
    foreach ([Booking::CONFIRMED, Booking::CHECKED_IN] as $state) {
        $svc->transition($booking, $state);
    }
    test()->travelTo(CarbonImmutable::parse($out.' 11:00'));
    $svc->transition($booking->refresh(), Booking::CHECKED_OUT);

    return $booking->refresh();
}

function finishedOrder(User $guest): Order
{
    $svc = app(OrderService::class);
    $order = $svc->place(test()->restaurant->refresh(), [['item_id' => test()->adobo->id, 'quantity' => 1], ['item_id' => test()->halo->id, 'quantity' => 2]], ['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => $guest->name], $guest);
    foreach ([Order::ACCEPTED, Order::PREPARING, Order::READY, Order::COMPLETED] as $s) {
        $svc->transition($order, $s);
    }

    return $order;
}

it('reviews each finished stay once with category and room ratings', function () {
    $first = finishedStay($this->guest);
    $review = reviews()->reviewStay($first, $this->guest, ['rating' => 5, 'comment' => 'Lovely', 'rating_cleanliness' => 5, 'rating_location' => 4, 'rating_food' => 1]);

    expect($review->room_type_id)->toBe($this->type->id)
        ->and($review->rating_cleanliness)->toBe(5)
        ->and($review->rating_food)->toBeNull() // not asked for stays
        ->and($review->tenant_id)->toBe($this->tenant->id)
        ->and(fn () => reviews()->reviewStay($first, $this->guest, ['rating' => 1, 'comment' => 'Again']))->toThrow(ValidationException::class)
        ->and(fn () => reviews()->reviewStay($first, User::factory()->create(), ['rating' => 1, 'comment' => 'Not mine']))->toThrow(ValidationException::class);

    // A second, separate stay can be reviewed too.
    $second = finishedStay($this->guest, '2030-10-01', '2030-10-02');
    reviews()->reviewStay($second, $this->guest, ['rating' => 3, 'comment' => 'Fine', 'rating_cleanliness' => 3]);

    expect($this->property->refresh()->reviews_count)->toBe(2)->and((float) $this->property->avg_rating)->toBe(4.0);
});

it('reviews orders with per-dish stars and table visits', function () {
    $order = finishedOrder($this->guest);
    $review = reviews()->reviewOrder($order, $this->guest, ['rating' => 4, 'comment' => 'Tasty', 'rating_food' => 5, 'rating_service' => 3], [$this->adobo->id => 5, $this->halo->id => 3, 99999 => 5]);

    expect($review->itemRatings()->pluck('rating', 'menu_item_id')->all())->toBe([$this->adobo->id => 5, $this->halo->id => 3])
        ->and($review->reviewable_type)->toBe('restaurant');

    $pending = app(OrderService::class)->place($this->restaurant, [['item_id' => $this->adobo->id, 'quantity' => 1]], ['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Maria'], $this->guest);
    expect(fn () => reviews()->reviewOrder($pending, $this->guest, ['rating' => 5, 'comment' => 'Too soon']))->toThrow(ValidationException::class);

    RestaurantTable::create(['restaurant_id' => $this->restaurant->id, 'label' => 'T1', 'seats' => 4, 'status' => 'active']);
    $visit = app(ReservationService::class)->reserve($this->restaurant, ['date' => '2030-09-06', 'time' => '19:00', 'party_size' => 2, 'guest_name' => 'Maria'], TableReservation::SOURCE_MARKETPLACE, $this->guest);
    expect(fn () => reviews()->reviewVisit($visit, $this->guest, ['rating' => 5, 'comment' => 'x']))->toThrow(ValidationException::class);
    app(ReservationService::class)->transition($visit, TableReservation::CONFIRMED);
    $this->travelTo(CarbonImmutable::parse('2030-09-06 19:05'));
    app(ReservationService::class)->transition($visit, TableReservation::SEATED);
    reviews()->reviewVisit($visit->refresh(), $this->guest, ['rating' => 5, 'comment' => 'Great service', 'rating_service' => 5]);

    $summary = reviews()->summary($this->restaurant->refresh());
    expect($summary['count'])->toBe(2)->and($summary['overall'])->toBe(4.5)
        ->and($summary['categories'])->toMatchArray(['food' => 5.0, 'service' => 4.0])
        ->and(array_keys($summary['dishes']))->toBe(['Adobo', 'Halo-halo']);
});

it('lets hosts reply and report, and admins hide reviews from the ratings', function () {
    $stay = finishedStay($this->guest);
    $bad = reviews()->reviewStay($stay, $this->guest, ['rating' => 1, 'comment' => 'Contains abuse']);
    $order = finishedOrder($other = User::factory()->create());
    reviews()->reviewOrder($order, $other, ['rating' => 5, 'comment' => 'Amazing']);

    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $this->get(route('reviews.index'))->assertOk()->assertSee('Contains abuse')->assertSee('Amazing');
    $this->post(route('reviews.reply', $bad->id), ['host_response' => 'Sorry to hear that.'])->assertSessionHasNoErrors();
    $this->post(route('reviews.flag', $bad->id), ['flag_reason' => 'Abusive language'])->assertSessionHasNoErrors();
    expect($this->guest->notifications()->where('type', \App\Modules\Reviews\Notifications\ReviewReplied::class)->count())->toBe(1);

    // Summary on the public page (host rating spans the business).
    MarketplaceFixtures::asTenant(null);
    auth()->logout();
    $this->get(route('marketplace.properties.show', $this->property->slug))->assertOk()->assertSee('1 verified review')->assertSee('host rating 3.0')->assertSee('Sorry to hear that.');

    // Moderation.
    $admin = User::factory()->create();
    $admin->roles()->syncWithoutDetaching([Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail()->id => ['tenant_id' => null]]);
    $this->actingAs($admin)->get(route('admin.reviews.index', ['flagged' => 1]))->assertOk()->assertSee('Abusive language');
    $this->post(route('admin.reviews.moderate', $bad->id), ['publish' => 0, 'note' => 'Community rules'])->assertSessionHasNoErrors();

    expect($bad->refresh()->status)->toBe(Review::STATUS_REJECTED)->and($bad->flagged_at)->toBeNull()
        ->and($this->property->refresh()->reviews_count)->toBe(0);
    $this->get(route('marketplace.properties.show', $this->property->slug))->assertDontSee('Contains abuse');

    $this->actingAs($this->guest)->get(route('admin.reviews.index'))->assertForbidden();
});

it('keeps review screens to the right people', function () {
    $review = reviews()->reviewStay(finishedStay($this->guest), $this->guest, ['rating' => 4, 'comment' => 'Good']);

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'staff'), $this->tenant);
    $this->get(route('reviews.index'))->assertForbidden();

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'front_desk'), $this->tenant);
    $this->get(route('reviews.index'))->assertOk();
    $this->post(route('reviews.reply', $review->id), ['host_response' => 'Thanks'])->assertForbidden();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('reviews.index'))->assertOk()->assertDontSee('Good');
    $this->post(route('reviews.reply', $review->id), ['host_response' => 'Not mine'])->assertNotFound();

    // The customer portal still offers the stay review form with categories.
    MarketplaceFixtures::asTenant(null);
    $stay = finishedStay($fresh = User::factory()->create(), '2030-11-01', '2030-11-03');
    $this->actingAs($fresh)->get(route('account.bookings.show', $stay->reference))->assertOk()->assertInertia(fn ($p) => $p->component('Account/Trip')->where('can.review', true));
    $this->post(route('account.bookings.review', $stay->reference), ['rating' => 5, 'comment' => 'Perfect', 'rating_value' => 5])->assertSessionHasNoErrors();
    expect(Review::query()->where('booking_id', $stay->id)->value('rating_value'))->toBe(5);
});
