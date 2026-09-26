<?php

use Illuminate\Support\Carbon;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;

/*
| OTA-style date search (Booking.com / Airbnb / Agoda research, 2026-09-26):
| only stays with a room free every night, the total price for the stay
| from the booking engine, honest "rooms left", free-cancellation filter,
| and the listing page quoting the same total.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    Carbon::setTestNow('2030-09-01 09:00:00');
    [, , $this->property, $this->type] = BookingFixtures::hotel(rooms: 1);
    MarketplaceFixtures::publish($this->property);
    $this->property->forceFill(['policies' => ['free_cancellation_days' => 3]])->save();
    BookingFixtures::reserve($this->property, $this->type); // occupies Sep 5, 6, 7
    MarketplaceFixtures::asTenant(null);
});

afterEach(fn () => Carbon::setTestNow());

it('hides stays that are full on the dates and shows the stay total for free ones', function () {
    $this->get(route('marketplace.hotels', ['check_in' => '2030-09-06', 'check_out' => '2030-09-08']))
        ->assertOk()
        ->assertDontSee($this->property->name);

    // Tue 10 + Wed 11 at 3500 = 7000 for two nights; the only room left is flagged.
    $this->get(route('marketplace.hotels', ['check_in' => '2030-09-10', 'check_out' => '2030-09-12']))
        ->assertOk()
        ->assertSee($this->property->name)
        ->assertSee('7,000')
        ->assertSee('total · 2 nights')
        ->assertSee('Only 1 room left for your dates')
        ->assertSee('Free cancellation');
});

it('filters for free cancellation and review score', function () {
    $this->get(route('marketplace.hotels', ['free_cancellation' => 1]))->assertSee($this->property->name);

    $this->property->forceFill(['policies' => []])->save();

    $this->get(route('marketplace.hotels', ['free_cancellation' => 1]))->assertDontSee($this->property->name);
    $this->get(route('marketplace.hotels', ['min_rating' => '4.5']))->assertDontSee($this->property->name);
});

it('quotes the same total on the listing page, with the free-cancellation deadline', function () {
    $this->get(route('marketplace.properties.show', ['property' => $this->property->slug, 'check_in' => '2030-09-10', 'check_out' => '2030-09-12', 'guests' => 2]))
        ->assertOk()
        ->assertSee('Total for your stay')
        ->assertSee('7,000')
        ->assertSee('Free cancellation until Sep 7, 2030');

    $this->get(route('marketplace.properties.show', ['property' => $this->property->slug, 'check_in' => '2030-09-05', 'check_out' => '2030-09-07']))
        ->assertOk()
        ->assertSee('Not available');
});

it('rejects impossible dates', function () {
    $this->get(route('marketplace.hotels', ['check_in' => '2030-09-12', 'check_out' => '2030-09-10']))->assertSessionHasErrors('check_out');
    $this->get(route('marketplace.hotels', ['check_in' => '2030-08-01', 'check_out' => '2030-08-03']))->assertSessionHasErrors('check_in');
});
