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

it('hides money from staff without accounting access', function () {
    $clerk = Tests\Support\MarketplaceFixtures::member($this->tenant, 'front_desk');
    Tests\Support\PropertyManagementFixtures::login($clerk, $this->tenant);

    $this->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('money', false)
            ->where('kpis', null)
            ->where('daily', [])
            ->where('portfolio', [])
            ->where('today.arrivals', 1));
});

it('lists every business in the portfolio for a group owner', function () {
    [, $second] = Tests\Support\MarketplaceFixtures::business('Hotel B');
    $second->users()->attach($this->owner->id, ['status' => 'active', 'joined_at' => now()]);
    $this->owner->assignTenantRole($second, $second->roles()->where('slug', 'owner')->firstOrFail());

    $this->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('portfolio', 2)
            ->where('portfolio.0.name', 'Hotel A')
            ->where('portfolio.0.current', true)
            ->where('portfolio.1.current', false));
});
