<?php

namespace App\Modules\PropertyManagement\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Property Management module (master plan §11: each domain is a
 * self-contained module owning its migrations, views, routes and policies).
 *
 * Host-side only in Phase 04: it manages the tenant-owned rows that the
 * Marketplace module publishes. Screens are React (Inertia) pages;
 * routes are declared in routes.php and loaded inside the `web` group.
 */
class PropertyManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
