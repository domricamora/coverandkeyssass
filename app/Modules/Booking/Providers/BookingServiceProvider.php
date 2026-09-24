<?php

namespace App\Modules\Booking\Providers;

use App\Modules\Booking\Controllers\ReservationController;
use App\Modules\Booking\Services\BookingService;
use App\Support\ModuleService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Booking Engine module (Phase 05): migrations, `booking::`
 * views, routes, and the reservation widget on the public property page.
 */
class BookingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'booking');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        // The marketplace owns the page; this module only adds the widget,
        // so the Marketplace module never depends on the booking engine.
        View::composer('marketplace::properties.show', function ($view): void {
            $property = $view->getData()['property'] ?? null;

            if (! $property || ! ReservationController::acceptsReservations($property, app(ModuleService::class))) {
                return;
            }

            $view->with('reservableRoomTypes', app(BookingService::class)->asTenantOf(
                $property,
                fn () => $property->roomTypes()->active()->sorted()->get(),
            ));
        });
    }
}
