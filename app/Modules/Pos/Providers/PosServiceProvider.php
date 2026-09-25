<?php

namespace App\Modules\Pos\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Boots the POS module (Phase 19): register, tickets, kitchen display, cash sessions. */
class PosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'pos');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
