<?php

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\Promotion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 05 (Booking Engine).
|
| Coverage: module gating, permissions, tenant isolation, nightly pricing
| (weekend + rate periods), double-booking prevention (application check
| and the room_nights unique index), blocks, multi-room/group capacity,
| the state machine, hold expiry, promo codes, walk-ins, the room calendar
| and marketplace reservation requests.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
});

it('refuses the booking desk while the booking module is not active', function () {
    [$owner, $tenant] = PropertyManagementFixtures::businessWithModule();

    $this->get(route('bookings.index'))->assertForbidden();

    BookingFixtures::enableModule($tenant);

    $this->get(route('bookings.index'))->assertOk();
});

it('creates a confirmed manual reservation priced per night with weekend rates', function () {
    [, , , $type] = BookingFixtures::hotel();

    // 2030-09-05 is a Thursday: Thu 3500 + Fri 4500 + Sat 4500.
    $this->post(route('bookings.store'), BookingFixtures::payload($type))->assertRedirect();

    $booking = Booking::query()->sole();

    expect($booking->status)->toBe(Booking::CONFIRMED)
        ->and((float) $booking->total)->toBe(12500.0)
        ->and($booking->confirmed_at)->not->toBeNull()
        ->and(DB::table('room_nights')->count())->toBe(3);
});

it('prices nights inside a rate period from the period', function () {
    [, , $property, $type] = BookingFixtures::hotel();
    PropertyManagementFixtures::ratePeriod($type); // Aug 1–10 2030 at 5000

    $booking = BookingFixtures::reserve($property, $type, ['check_in' => '2030-08-01', 'check_out' => '2030-08-03']);

    expect((float) $booking->total)->toBe(10000.0);
});

it('never sells the same room twice for overlapping nights', function () {
    [, , $property, $type] = BookingFixtures::hotel(rooms: 1);

    BookingFixtures::reserve($property, $type);

    expect(fn () => BookingFixtures::reserve($property, $type, ['check_in' => '2030-09-07', 'check_out' => '2030-09-09']))
        ->toThrow(ValidationException::class, 'sold out');

    // Back-to-back is fine: check-out day is not a night.
    $next = BookingFixtures::reserve($property, $type, ['check_in' => '2030-09-08', 'check_out' => '2030-09-10']);

    expect($next->status)->toBe(Booking::CONFIRMED);
});

it('rejects a duplicate room-night at the database level', function () {
    [, , $property, $type] = BookingFixtures::hotel();

    $booking = BookingFixtures::reserve($property, $type);
    $line = DB::table('booking_rooms')->where('booking_id', $booking->id)->first();

    expect(fn () => DB::table('room_nights')->insert([
        'booking_room_id' => $line->id,
        'room_id' => $line->room_id,
        'night' => '2030-09-06',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('treats availability blocks as unsellable', function () {
    [, , $property, $type] = BookingFixtures::hotel();
    PropertyManagementFixtures::block($type, ['start_date' => '2030-09-06', 'end_date' => '2030-09-06']);

    expect(fn () => BookingFixtures::reserve($property, $type))->toThrow(ValidationException::class, 'sold out');
});

it('books several rooms for a group and assigns distinct rooms', function () {
    [, , $property, $type] = BookingFixtures::hotel(rooms: 3);

    $booking = BookingFixtures::reserve($property, $type, [
        'rooms' => [['room_type_id' => $type->id, 'quantity' => 2]],
        'adults' => 4,
        'group_name' => 'Santos wedding',
    ]);

    expect($booking->rooms()->pluck('room_id')->unique())->toHaveCount(2)
        ->and((float) $booking->total)->toBe(25000.0);

    // One room left — asking for two more fails, one more succeeds.
    expect(fn () => BookingFixtures::reserve($property, $type, ['rooms' => [['room_type_id' => $type->id, 'quantity' => 2]]]))
        ->toThrow(ValidationException::class, 'Only 1');
});

it('rejects more guests than the rooms sleep', function () {
    [, , $property, $type] = BookingFixtures::hotel();

    expect(fn () => BookingFixtures::reserve($property, $type, ['adults' => 3]))
        ->toThrow(ValidationException::class, 'sleep at most 2');
});

it('releases the rooms when a booking is cancelled', function () {
    [, , $property, $type] = BookingFixtures::hotel();
    $booking = BookingFixtures::reserve($property, $type);

    $this->post(route('bookings.transition', $booking->reference), ['status' => 'cancelled', 'reason' => 'Guest called'])
        ->assertRedirect();

    expect($booking->refresh()->status)->toBe(Booking::CANCELLED)
        ->and($booking->cancellation_reason)->toBe('Guest called')
        ->and(DB::table('room_nights')->count())->toBe(0);

    expect(BookingFixtures::reserve($property, $type)->status)->toBe(Booking::CONFIRMED);
});

it('enforces the state machine', function () {
    [, , $property, $type] = BookingFixtures::hotel();
    $booking = BookingFixtures::reserve($property, $type);

    // Not before the check-in date, and never straight to checked out.
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_in'])->assertSessionHasErrors('status');
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_out'])->assertSessionHasErrors('status');

    $this->travelTo('2030-09-05 15:00');
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_in'])->assertSessionHasNoErrors();

    // Early departure on the 6th frees the nights of the 6th and 7th.
    $this->travelTo('2030-09-06 10:00');
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'checked_out'])->assertSessionHasNoErrors();
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'completed'])->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::COMPLETED)
        ->and(DB::table('room_nights')->pluck('night')->map(fn ($n) => substr($n, 0, 10))->all())->toBe(['2030-09-05']);
});

it('marks no-shows only from the check-in date and allows a refund after', function () {
    [, , $property, $type] = BookingFixtures::hotel();
    $booking = BookingFixtures::reserve($property, $type);

    $this->post(route('bookings.transition', $booking->reference), ['status' => 'no_show'])->assertSessionHasErrors('status');

    $this->travelTo('2030-09-06 09:00');
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'no_show'])->assertSessionHasNoErrors();
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'refunded'])->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::REFUNDED)
        ->and(DB::table('room_nights')->count())->toBe(0);
});

it('lets an expired hold go back on sale', function () {
    [, , $property, $type] = BookingFixtures::hotel();
    $hold = BookingFixtures::reserve($property, $type, ['hold_hours' => 1]);

    expect($hold->status)->toBe(Booking::HELD);

    $this->travel(2)->hours();

    $booking = BookingFixtures::reserve($property, $type);

    expect($booking->status)->toBe(Booking::CONFIRMED)
        ->and($hold->refresh()->status)->toBe(Booking::CANCELLED)
        ->and($hold->cancellation_reason)->toBe('Hold expired');
});

it('applies a promo code and counts its use', function () {
    [, , $property, $type] = BookingFixtures::hotel();

    $this->post(route('bookings.promotions.store'), [
        'code' => 'summer10', 'name' => 'Summer', 'type' => 'percent', 'value' => 10, 'max_uses' => 1,
    ])->assertSessionHasNoErrors();

    $booking = BookingFixtures::reserve($property, $type, ['promo_code' => 'SUMMER10']);

    expect((float) $booking->discount_total)->toBe(1250.0)
        ->and((float) $booking->total)->toBe(11250.0)
        ->and(Promotion::query()->sole()->used_count)->toBe(1);

    // Fully redeemed now.
    expect(fn () => BookingFixtures::reserve($property, $type, ['check_in' => '2030-10-01', 'check_out' => '2030-10-02', 'promo_code' => 'SUMMER10']))
        ->toThrow(ValidationException::class, 'fully redeemed');
});

it('checks a walk-in guest in immediately', function () {
    [, , , $type] = BookingFixtures::hotel();
    $this->travelTo('2030-09-05 12:00');

    $this->post(route('bookings.store'), BookingFixtures::payload($type, ['source' => 'walk_in']))->assertSessionHasNoErrors();

    expect(Booking::query()->sole())
        ->status->toBe(Booking::CHECKED_IN)
        ->checked_in_at->not->toBeNull();
});

it('lets front desk take bookings but not manage promotions, and keeps staff out', function () {
    [$owner, $tenant, , $type] = BookingFixtures::hotel();

    $desk = MarketplaceFixtures::member($tenant, 'front_desk');
    PropertyManagementFixtures::login($desk, $tenant);

    $this->post(route('bookings.store'), BookingFixtures::payload($type))->assertRedirect();
    $this->get(route('bookings.promotions.index'))->assertForbidden();

    $staff = MarketplaceFixtures::member($tenant, 'staff');
    PropertyManagementFixtures::login($staff, $tenant);

    $this->get(route('bookings.index'))->assertForbidden();
});

it('hides another business booking behind a 404', function () {
    [, , $propertyB, $typeB] = BookingFixtures::hotel(name: 'Hotel B');
    $foreign = BookingFixtures::reserve($propertyB, $typeB);

    BookingFixtures::hotel(name: 'Hotel A');

    $this->get(route('bookings.show', $foreign->reference))->assertNotFound();
    $this->post(route('bookings.transition', $foreign->reference), ['status' => 'cancelled'])->assertNotFound();
});

it('shows bookings on the list and the room calendar', function () {
    [, , $property, $type] = BookingFixtures::hotel(rooms: 2);
    $booking = BookingFixtures::reserve($property, $type, ['guest_name' => 'Maria Clara']);

    $this->get(route('bookings.index'))->assertOk()->assertSee('Maria Clara');
    $this->get(route('bookings.show', $booking->reference))->assertOk()->assertSee('Check in')->assertSee('PHP 12,500.00');
    $this->get(route('bookings.create', ['property' => $property->slug, 'check_in' => '2030-09-05', 'check_out' => '2030-09-08']))
        ->assertOk()
        ->assertSeeInOrder(['Deluxe Room', 'PHP 3,500', '<td>1</td>'], false); // 1 of 2 rooms free
    $this->get(route('bookings.calendar', ['property' => $property->slug, 'start' => '2030-09-04']))
        ->assertOk()
        ->assertSee('Maria Cl');
});

it('lets a signed-in guest request a stay from the marketplace', function () {
    [, , $property, $type] = BookingFixtures::hotel();
    MarketplaceFixtures::publish($property);
    $guest = User::factory()->create();

    $this->actingAs($guest)
        ->get(route('marketplace.properties.show', $property->slug))
        ->assertOk()
        ->assertSee("You won't be charged yet", false);

    $this->post(route('marketplace.properties.reserve', $property->slug), [
        'check_in' => '2030-09-05', 'check_out' => '2030-09-08',
        'room_type_id' => $type->id, 'quantity' => 1, 'adults' => 2,
    ])->assertRedirect();

    $booking = Booking::forCustomer($guest)->sole();

    expect($booking->status)->toBe(Booking::PENDING)
        ->and($booking->source)->toBe(Booking::SOURCE_MARKETPLACE)
        ->and($booking->guest_email)->toBe($guest->email);
});

it('refuses marketplace reservations when the business does not run the booking module', function () {
    [$property, $owner, $tenant] = MarketplaceFixtures::stay();
    PropertyManagementFixtures::enableModule($tenant);
    $type = PropertyManagementFixtures::roomType($property);

    $this->actingAs(User::factory()->create())
        ->post(route('marketplace.properties.reserve', $property->slug), [
            'check_in' => '2030-09-05', 'check_out' => '2030-09-08',
            'room_type_id' => $type->id, 'quantity' => 1, 'adults' => 1,
        ])->assertNotFound();
});
