<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(AuditLogger::class);
    }

    public function boot(): void
    {
        // Portable string lengths for indexed columns (older MySQL 5.6 setups).
        Schema::defaultStringLength(191);

        // Ensure every new MySQL connection creates InnoDB tables regardless of
        // the server default (WAMP ships with MyISAM, which ignores foreign keys).
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Database\Events\ConnectionEstablished::class,
            function (\Illuminate\Database\Events\ConnectionEstablished $event) {
                if ($event->connection->getDriverName() === 'mysql') {
                    $event->connection->statement('SET SESSION default_storage_engine = InnoDB');
                }
            },
        );

        // Model policies.
        Gate::policy(Tenant::class, TenantPolicy::class);

        // Platform super admins bypass per-tenant permission checks.
        // The EnsureSuperAdmin middleware remains the authoritative gate
        // for /admin routes; this only simplifies tenant-side checks.
        Gate::before(function (User $user, string $ability) {
            return $user->isPlatformAdmin() ? true : null;
        });
    }
}

