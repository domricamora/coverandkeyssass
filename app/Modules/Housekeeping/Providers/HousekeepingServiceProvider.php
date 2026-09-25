<?php

namespace App\Modules\Housekeeping\Providers;

use App\Modules\Booking\Events\BookingTransitioned;
use App\Modules\Booking\Models\Booking;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Housekeeping module (Phase 15). A booking checking out makes
 * its rooms dirty and queues a checkout clean.
 */
class HousekeepingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../Maintenance/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'housekeeping');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        Event::listen(function (BookingTransitioned $event): void {
            if ($event->to === Booking::CHECKED_OUT) {
                app(TenantContext::class)->runAs($event->booking, fn () => app(HousekeepingService::class)->onCheckout($event->booking));
            }
        });
    }
}
