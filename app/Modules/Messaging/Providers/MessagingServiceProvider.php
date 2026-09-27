<?php

namespace App\Modules\Messaging\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Boots Messaging (Phase 25): guest ↔ business, guest ↔ support and staff threads. */
class MessagingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
