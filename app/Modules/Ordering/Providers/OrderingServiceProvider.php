<?php

namespace App\Modules\Ordering\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Ordering module (Phase 11): cart, checkout, orders and the
 * host order queue. Payments listens to OrderTransitioning (refunds) and
 * Wallet to OrderTransitioned (release on completed).
 */
class OrderingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
