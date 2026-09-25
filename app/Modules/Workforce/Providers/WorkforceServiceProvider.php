<?php

namespace App\Modules\Workforce\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Workforce module (Phase 17): employees, departments,
 * positions, roster, attendance, leave and "My work".
 */
class WorkforceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'workforce');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
