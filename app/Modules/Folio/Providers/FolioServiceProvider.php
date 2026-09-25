<?php

namespace App\Modules\Folio\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Folio module (Phase 14): one ledger per booking built from
 * room nights, payments and room charges plus desk postings.
 */
class FolioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'folio');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
