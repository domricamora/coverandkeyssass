<?php

namespace App\Modules\Maintenance\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Maintenance module (Phase 16, part of `workforce`): tickets,
 * notes, private attachments, cost and the status workflow.
 */
class MaintenanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'maintenance');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
