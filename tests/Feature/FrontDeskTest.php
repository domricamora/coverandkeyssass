<?php

use App\Modules\Booking\Models\Booking;
use App\Modules\PropertyManagement\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

/*
| Front desk (React/Inertia rebuild): today's board, tape chart, the
| booking drawer with its folio, and moving a stay to another room.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    Carbon::setTestNow('2030-09-05 10:00:00');
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(rooms: 3);
    $this->stay = BookingFixtures::reserve($this->property, $this->type); // Sep 5–8, room 101
});

afterEach(fn () => Carbon::setTestNow());

function nightsIn(string $room): array
{
    return DB::table('room_nights')->join('rooms', 'rooms.id', '=', 'room_nights.room_id')
        ->where('rooms.room_number', $room)->orderBy('night')->pluck('night')
        ->map(fn ($n) => substr((string) $n, 0, 10))->all();
}

it('shows today\'s arrivals, occupancy and the tape chart', function () {
    $this->get(route('frontdesk.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FrontDesk/Index')
            ->where('today', '2030-09-05')
            ->has('lists.arrivals', 1)
            ->where('lists.arrivals.0.reference', $this->stay->reference)
            ->where('lists.arrivals.0.balance', 12500)
            ->where('stats.occupancy', 33)
            ->has('rooms', 3)
            ->has('stays', 1)
            ->where('stays.0.first', '2030-09-05')
            ->where('stays.0.last', '2030-09-07')
            ->where('selected', null)
            ->has('nav'));
});

it('opens a booking with its folio and checks the guest in from the desk', function () {
    $this->get(route('frontdesk.index', ['booking' => $this->stay->reference]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.reference', $this->stay->reference)
            ->where('selected.next', ['checked_in', 'cancelled', 'no_show'])
            ->where('selected.folio.totals.balance', 12500)
            ->has('selected.folio.entries', 3));

    $this->from(route('frontdesk.index'))
        ->post(route('bookings.transition', $this->stay->reference), ['status' => Booking::CHECKED_IN])
        ->assertRedirect(route('frontdesk.index'));

    $this->get(route('frontdesk.index'))
        ->assertInertia(fn (Assert $page) => $page->has('lists.inHouse', 1)->where('stats.arrivalsLeft', 0));
});

it('moves a stay to another room, and only the nights not slept yet', function () {
    $bookingRoom = $this->stay->rooms()->sole();
    $target = Room::query()->where('room_number', '102')->sole();

    app(\App\Modules\Booking\Services\BookingService::class)->transition($this->stay, Booking::CHECKED_IN);
    Carbon::setTestNow('2030-09-06 09:00:00');

    $this->from(route('frontdesk.index'))
        ->post(route('frontdesk.move'), ['booking_room' => $bookingRoom->id, 'room_id' => $target->id])
        ->assertRedirect(route('frontdesk.index'))
        ->assertSessionHas('success');

    expect(nightsIn('101'))->toBe(['2030-09-05'])
        ->and(nightsIn('102'))->toBe(['2030-09-06', '2030-09-07'])
        ->and($bookingRoom->refresh()->room_id)->toBe($target->id);
});

it('refuses a move onto a taken or out-of-order room', function () {
    $other = BookingFixtures::reserve($this->property, $this->type, ['guest_name' => 'Maria Santos']); // gets 102
    $taken = $other->rooms()->sole()->room;
    $broken = Room::query()->where('room_number', '103')->sole();
    $broken->forceFill(['housekeeping_status' => Room::HK_OUT_OF_ORDER])->save();
    $bookingRoom = $this->stay->rooms()->sole();

    $this->from(route('frontdesk.index'))
        ->post(route('frontdesk.move'), ['booking_room' => $bookingRoom->id, 'room_id' => $taken->id])
        ->assertSessionHasErrors(['room_id' => 'Room '.$taken->room_number.' is taken on some of these nights.']);

    $this->post(route('frontdesk.move'), ['booking_room' => $bookingRoom->id, 'room_id' => $broken->id])
        ->assertSessionHasErrors('room_id');

    expect(nightsIn('101'))->toHaveCount(3);
});

it('keeps another business\'s bookings and rooms out of reach', function () {
    $bookingRoom = $this->stay->rooms()->sole();

    BookingFixtures::hotel(rooms: 1, name: 'Hotel B'); // now signed in as Hotel B's owner
    $theirRoom = Room::query()->where('room_number', '101')->sole(); // B's own room 101

    $this->post(route('frontdesk.move'), ['booking_room' => $bookingRoom->id, 'room_id' => $theirRoom->id])->assertNotFound();

    $this->get(route('frontdesk.index', ['booking' => $this->stay->reference]))->assertNotFound();

    $this->get(route('frontdesk.index'))
        ->assertInertia(fn (Assert $page) => $page->has('lists.arrivals', 0)->has('stays', 0));
});

it('keeps the business sidebar on the notifications page', function () {
    // Regression: the page ran without tenant context, so the sidebar lost every business section.
    app(\App\Support\TenantContext::class)->forget(); // fixtures set it in-process; a real request starts empty
    $this->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('nav', fn ($nav) => collect($nav)->contains(fn ($g) => $g['label'] === 'Hotel'
            && collect($g['items'])->contains('href', route('frontdesk.index')))));
});
