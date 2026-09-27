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
        ->and(Media::query()->whereIn('mediable_type', ['property', 'restaurant'])->where('path', 'like', 'img/demo/%')->count())->toBe(176)          // 3 own + 8 tour photos x 16 listings (room and dish photos are extra)
        ->and(Media::query()->where('path', 'like', 'img/demo/tour/%')->whereNull('caption')->exists())->toBeFalse()
        ->and(DB::table('tenant_modules')->whereNotNull('expires_at')->exists())->toBeFalse()
        ->and(DB::table('housekeeping_tasks')->exists())->toBeTrue()          // queued by real check-outs
        ->and(DB::table('folio_entries')->where('type', 'payment')->exists())->toBeTrue();

    // A full team per business (not one person), with logins, a rota, clock-ins and register history.
    $perTenant = DB::table('employees')->selectRaw('tenant_id, count(*) c, count(user_id) u')->groupBy('tenant_id')->get();
    expect($perTenant)->toHaveCount(4)
        ->and($perTenant->every(fn ($r) => $r->c === 20 && $r->u >= 15))->toBeTrue()
        ->and(DB::table('employees')->distinct()->count('name'))->toBe(80)
        ->and(DB::table('attendances')->whereNotNull('clock_out_at')->exists())->toBeTrue()
        ->and(DB::table('shifts')->where('starts_at', '>', now())->exists())->toBeTrue()
        ->and(DB::table('pos_sessions')->whereNotNull('closed_at')->exists())->toBeTrue()
        // Regression: time travel must not drift the calendar, and past register tickets are all settled.
        ->and(DB::table('bookings')->where('check_in', '<', today()->subDays(30))->exists())->toBeFalse()
        ->and(DB::table('orders')->where('created_at', '<', today()->subDays(30))->exists())->toBeFalse()
        ->and(DB::table('orders')->where('channel', 'pos')->where('created_at', '<', today())->whereIn('status', ['accepted', 'preparing', 'ready'])->exists())->toBeFalse()
        ->and(DB::table('tenant_users')->where('user_id', App\Models\User::query()->where('email', 'group@coverandkeys.example.test')->value('id'))->count())->toBe(4);

    $count = $bookings()->count();
    $media = Media::query()->count();
    $this->seed(MarketplaceDemoSeeder::class);

    expect($bookings()->count())->toBe($count)
        ->and(Media::query()->count())->toBe($media);

    $this->get(route('marketplace.properties.show', 'aplaya-beachfront-suites'))
        ->assertOk()->assertSee('Take a look around')->assertSee('Show all 11 photos')->assertSee('Bathrooms');
});
