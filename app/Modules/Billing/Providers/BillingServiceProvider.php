<?php

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Services\BillingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots SaaS billing (Phase 27). `php artisan billing:run` (daily) renews
 * periods, marks overdue invoices, suspends after grace and ends trials.
 */
class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            Artisan::command('billing:run', function (): void {
                $stats = app(BillingService::class)->run();
                $this->info(collect($stats)->map(fn ($n, $k) => str_replace('_', ' ', $k).': '.$n)->implode(' · '));
            })->purpose('Renew subscriptions, flag overdue invoices, suspend unpaid modules and end trials');
        }
    }
}
