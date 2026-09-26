<?php

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

/*
| Owner overview (React dashboard home): today's pulse and period KPIs.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    Carbon::setTestNow('2030-09-05 10:00:00');
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(rooms: 3);
    BookingFixtures::reserve($this->property, $this->type); // Sep 5–8
});

afterEach(fn () => Carbon::setTestNow());

it('shows today and occupancy for the chosen period', function () {
    $this->get(route('dashboard', ['range' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overview/Index')
            ->where('range', 7)
            ->where('money', true)
            ->where('today.arrivals', 1)
            ->where('kpis.rooms', 3)
            ->where('kpis.nights_sold', 1)       // only Sep 5 falls in Aug 30 – Sep 5
            ->where('kpis.occupancy', 4.8)       // 1 / (3 rooms × 7 days)
            ->has('daily', 7)
            ->where('daily.6.date', '2030-09-05')
            ->where('portfolio', []));
});

it('falls back to 30 days for an unknown range', function () {
    $this->get(route('dashboard', ['range' => 5]))
        ->assertInertia(fn (Assert $page) => $page->where('range', 30)->has('daily', 30));
});
