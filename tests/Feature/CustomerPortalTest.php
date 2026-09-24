<?php

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Review;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;

/*
| Phase 06 (Customer Portal).
|
| Coverage: authentication, trips isolation between guests, guest
| cancellation rules, host-status notifications, verified-stay reviews
| and the invoice page.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
});

/** A marketplace guest with a pending booking at a fresh hotel. */
function guestTrip(array $attributes = []): array
{
    [, , $property, $type] = BookingFixtures::hotel();
    $guest = User::factory()->create(['name' => 'Guest One']);
    $booking = BookingFixtures::reserve($property, $type, $attributes, Booking::SOURCE_MARKETPLACE, $guest);

    return [$guest, $booking, $property, $type];
}

it('requires authentication for the account area', function () {
    $this->get(route('account.dashboard'))->assertRedirect(route('login'));
});

it('shows a guest their own trips only', function () {
    [$guest, $booking, $property, $type] = guestTrip();
    $other = User::factory()->create();
    $foreign = BookingFixtures::reserve($property, $type, ['check_in' => '2030-10-01', 'check_out' => '2030-10-03'], Booking::SOURCE_MARKETPLACE, $other);

    $this->actingAs($guest)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee($booking->reference)
        ->assertDontSee($foreign->reference);

    $this->get(route('account.bookings.show', $foreign->reference))->assertNotFound();
    $this->post(route('account.bookings.cancel', $foreign->reference))->assertNotFound();
});

it('lets a guest cancel before check-in and frees the rooms', function () {
    [$guest, $booking] = guestTrip();

    $this->actingAs($guest)
        ->post(route('account.bookings.cancel', $booking->reference))
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::CANCELLED)
        ->and($booking->cancellation_reason)->toBe('Cancelled by guest')
        ->and(DB::table('room_nights')->count())->toBe(0);

    $this->get(route('account.bookings.index', ['tab' => 'past']))->assertSee($booking->reference);
});

it('refuses online cancellation from the check-in date', function () {
    [$guest, $booking] = guestTrip();
    $this->travelTo('2030-09-05 08:00');

    $this->actingAs($guest)
        ->post(route('account.bookings.cancel', $booking->reference))
        ->assertSessionHasErrors('booking');

    expect($booking->refresh()->status)->toBe(Booking::PENDING);
});

it('notifies the guest when the host confirms', function () {
    [$guest, $booking] = guestTrip();

    // Host session is still active from BookingFixtures::hotel().
    $this->post(route('bookings.transition', $booking->reference), ['status' => 'confirmed'])->assertSessionHasNoErrors();

    expect($guest->unreadNotifications()->count())->toBe(1);

    $this->actingAs($guest)
        ->get(route('account.notifications'))
        ->assertOk()
        ->assertSee('is now confirmed');

    $this->post(route('account.notifications.read'));

    expect($guest->unreadNotifications()->count())->toBe(0);
});

it('lets a guest review a finished stay once', function () {
    [$guest, $booking, $property] = guestTrip();

    $this->actingAs($guest)
        ->post(route('account.bookings.review', $booking->reference), ['rating' => 5, 'comment' => 'Great'])
        ->assertForbidden();

    $service = app(BookingService::class);
    $this->travelTo('2030-09-05 15:00');
    $service->asTenantOf($booking, function () use ($service, $booking) {
        foreach ([Booking::CONFIRMED, Booking::CHECKED_IN, Booking::CHECKED_OUT] as $to) {
            $service->transition($booking, $to);
        }
    });

    $this->post(route('account.bookings.review', $booking->reference), ['rating' => 4, 'comment' => 'Lovely rooms'])
        ->assertSessionHasNoErrors();
    $this->post(route('account.bookings.review', $booking->reference), ['rating' => 1, 'comment' => 'Again'])
        ->assertSessionHasErrors('rating');

    expect(Review::query()->sole())
        ->status->toBe(Review::STATUS_PUBLISHED)
        ->rating->toBe(4);

    $this->get(route('account.reviews'))->assertOk()->assertSee('Lovely rooms');
});

it('renders an invoice for the guest', function () {
    [$guest, $booking] = guestTrip();

    $this->actingAs($guest)
        ->get(route('account.bookings.invoice', $booking->reference))
        ->assertOk()
        ->assertSee($booking->reference)
        ->assertSee('PHP 12,500.00');
});
