<?php

namespace App\Modules\Accounting\Providers;

use App\Models\Tenant;
use App\Modules\Accounting\Services\PostingService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Accounting module (Phase 20). `php artisan accounting:sync`
 * books the automatic postings for every business (idempotent; the report
 * screens also sync on view).
 */
class AccountingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'accounting');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            Artisan::command('accounting:sync', function (): void {
                $context = app(TenantContext::class);

                foreach (Tenant::query()->get() as $tenant) {
                    $context->set($tenant);
                    app(PostingService::class)->sync();
                    $this->info('Synced '.$tenant->name);
                }

                $context->forget();
            })->purpose('Book automatic accounting postings for every business');
        }
    }
}
