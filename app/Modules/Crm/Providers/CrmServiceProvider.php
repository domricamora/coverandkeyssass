<?php

namespace App\Modules\Crm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Boots the CRM module (Phase 21): guest contacts, segments, notes, communication history. */
class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'crm');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
