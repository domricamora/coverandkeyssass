<?php

namespace App\Modules\Customer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Customer Portal module (Phase 06): the guest-facing `/account`
 * area. Views are namespaced `customer::`.
 */
class CustomerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'customer');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
