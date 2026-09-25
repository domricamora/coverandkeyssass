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

        $this->hardenForProduction();
        $this->tracePerformance();
        $this->logAuthentication();

        // Model policies.
        Gate::policy(Tenant::class, TenantPolicy::class);

        // Module gating for tenant feature areas (e.g. `module.active:property`).
        \Illuminate\Support\Facades\Route::aliasMiddleware(
            'module.active',
            \App\Http\Middleware\EnsureModuleActive::class,
        );

        // Platform super admins bypass per-tenant permission checks.
        // The EnsureSuperAdmin middleware remains the authoritative gate
        // for /admin routes; this only simplifies tenant-side checks.
        Gate::before(function (User $user, string $ability) {
            return $user->isPlatformAdmin() ? true : null;
        });
    }

    /**
     * Phase 37: login attempts to the `security` log. Emails only (lower-cased),
     * never passwords; lockouts are alerts (brute force being throttled).
     */
    private function logAuthentication(): void
    {
        $security = fn () => \Illuminate\Support\Facades\Log::channel('security');

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, fn ($e) => $security()->warning('auth.login_failed', [
            'email' => mb_strtolower((string) ($e->credentials['email'] ?? '')),
            'known_user' => $e->user !== null,
            'ip' => request()->ip(),
        ]));

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Lockout::class, fn ($e) => $security()->alert('auth.lockout', [
            'email' => mb_strtolower((string) $e->request->input('email')),
            'ip' => $e->request->ip(),
        ]));

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn ($e) => $security()->info('auth.login', [
            'user_id' => $e->user->getAuthIdentifier(),
            'guard' => $e->guard,
            'ip' => request()->ip(),
        ]));
    }

    /**
     * Dev-only performance trace (Phase 33), off unless PERF_TRACE=true and
     * never in production: logs every lazy-loaded relation (N+1 suspect) and
     * each request's query count / time to the `perf` log channel.
     */
    private function tracePerformance(): void
    {
        if ($this->app->isProduction() || ! env('PERF_TRACE')) {
            return;
        }

        $log = \Illuminate\Support\Facades\Log::build(['driver' => 'single', 'path' => storage_path('logs/perf.log')]);

        \Illuminate\Database\Eloquent\Model::preventLazyLoading();
        \Illuminate\Database\Eloquent\Model::handleLazyLoadingViolationUsing(
            fn ($model, string $relation) => $log->warning('lazy-load '.get_class($model).'::'.$relation.' '.request()->path()),
        );

        $queries = 0;
        $ms = 0.0;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries, &$ms) {
            $queries++;
            $ms += $query->time;
        });

        $this->app->terminating(function () use (&$queries, &$ms, $log) {
            if (! $this->app->runningInConsole()) {
                $log->info(sprintf('queries=%d db_ms=%.1f %s', $queries, $ms, request()->path()));
            }
        });
    }

    /**
     * Phase 32 security baseline. Password rules apply everywhere (Breeze
     * forms use Password::defaults()); the breach check (Have I Been Pwned,
     * k-anonymity range API) and HTTPS forcing only in production.
     */
    private function hardenForProduction(): void
    {
        \Illuminate\Validation\Rules\Password::defaults(function () {
            $rule = \Illuminate\Validation\Rules\Password::min(10)->letters()->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        if ($this->app->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');

            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::critical('APP_DEBUG is enabled in production: stack traces and config are exposed. Set APP_DEBUG=false.');
            }
        }
    }
}

