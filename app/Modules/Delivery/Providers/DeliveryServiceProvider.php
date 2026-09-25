<?php

namespace App\Modules\Delivery\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Delivery module (Phase 12): zones, drivers and the delivery
 * columns on orders. Pricing and dispatch rules run in OrderService.
 */
class DeliveryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'delivery');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
