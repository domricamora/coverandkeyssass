<?php

use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Media;
use App\Modules\Ordering\Models\Order;
use Database\Seeders\MarketplaceDemoSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/*
| The demo seed is what the owner clicks through on localhost, so it must keep
| working as the domain evolves: real photos, bookable rooms, every module on,
| operational data in every state, and a safe re-run.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('seeds a fully explorable demo through the real services, and re-runs safely', function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class, ModuleSeeder::class, MarketplaceDemoSeeder::class]);

    $bookings = fn () => Booking::query()->withoutGlobalScopes()->get();
    $statuses = $bookings()->pluck('status')->unique();

    expect($statuses)->toContain(Booking::CHECKED_OUT, Booking::CHECKED_IN, Booking::CONFIRMED, Booking::PENDING)
        ->and(Order::query()->withoutGlobalScopes()->where('status', Order::COMPLETED)->exists())->toBeTrue()
        ->and(Media::query()->where('path', 'like', 'img/demo/%')->count())->toBe(110)          // 3 own + 8 tour photos x 10 listings
        ->and(Media::query()->where('path', 'like', 'img/demo/tour/%')->whereNull('caption')->exists())->toBeFalse()
        ->and(DB::table('tenant_modules')->whereNotNull('expires_at')->exists())->toBeFalse()
        ->and(DB::table('housekeeping_tasks')->exists())->toBeTrue()          // queued by real check-outs
        ->and(DB::table('folio_entries')->where('type', 'payment')->exists())->toBeTrue();

    $count = $bookings()->count();
    $this->seed(MarketplaceDemoSeeder::class);

    expect($bookings()->count())->toBe($count)
        ->and(Media::query()->count())->toBe(110);

    $this->get(route('marketplace.properties.show', 'aplaya-beachfront-suites'))
        ->assertOk()->assertSee('Take a look around')->assertSee('Show all 11 photos')->assertSee('Bathrooms');
});
