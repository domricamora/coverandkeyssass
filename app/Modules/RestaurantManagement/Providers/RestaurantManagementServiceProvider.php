<?php

namespace App\Modules\RestaurantManagement\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Restaurant Management module (Phase 09): host-side profile,
 * menu builder and floor plan for the Marketplace `Restaurant` listing.
 * Views are namespaced `restaurant-management::`.
 */
class RestaurantManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'restaurant-management');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
