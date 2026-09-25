<?php

use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Services\CrmService;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;

/*
| Phase 33 (Performance) — budgets that catch regressions: the dashboard shell
| resolves permissions once per request, and the incremental CRM sync still
| folds in guests who appear after the previous sync.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => BookingFixtures::bootstrap());

it('renders a sidebar-heavy dashboard page within a query budget', function () {
    BookingFixtures::hotel();

    $queries = 0;
    DB::listen(function () use (&$queries) { $queries++; });

    $this->get(route('bookings.index'))->assertOk();

    // ~20 permission checks used to cost 3 queries each (60+); now one set per request.
    expect($queries)->toBeLessThan(30);
});

it('keeps the CRM watermark usable on a serialising cache store', function () {
    // Regression: a Carbon watermark came back from the database cache as
    // __PHP_Incomplete_Class and crashed the Guests page on the second visit.
    config(['cache.default' => 'database']);
    [, , $property, $type] = BookingFixtures::hotel();
    BookingFixtures::reserve($property, $type, ['guest_name' => 'Cara Lim', 'guest_email' => 'cara@example.test']);

    $crm = app(CrmService::class);
    $crm->sync();
    $crm->sync();

    expect(Contact::query()->where('email', 'cara@example.test')->exists())->toBeTrue();
});

it('syncs CRM incrementally without missing guests who arrive after the last sync', function () {
    [, , $property, $type] = BookingFixtures::hotel(rooms: 2);
    $crm = app(CrmService::class);

    BookingFixtures::reserve($property, $type, ['guest_name' => 'Ana Reyes', 'guest_email' => 'ana@example.test']);
    $crm->sync();

    $this->travel(10)->seconds();
    BookingFixtures::reserve($property, $type, ['check_in' => '2030-10-01', 'check_out' => '2030-10-03', 'guest_name' => 'Ben Cruz', 'guest_email' => 'ben@example.test']);
    $crm->sync();

    // Ben only exists after the second, incremental run. (bookings_count counts
    // stays — checked in / out — so a fresh reservation is correctly 0.)
    expect(Contact::query()->pluck('email')->sort()->values()->all())->toBe(['ana@example.test', 'ben@example.test']);
});
