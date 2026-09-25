<?php

namespace App\Modules\Api\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Public REST API, version 1 (Phase 31) at /api/v1.
 *
 * Stateless: Sanctum personal access tokens (Bearer), no session or CSRF.
 * Three surfaces: the public catalogue, `/me` (the signed-in customer) and
 * `/business` (staff of the business named in the `X-Tenant` header). All
 * writes go through the same services and permissions as the web app.
 */
class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 120 requests / minute per token (or IP when anonymous).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        Route::prefix('api/v1')
            ->middleware(['api', 'throttle:api'])
            ->name('api.')
            ->group(__DIR__.'/../routes.php');
    }
}
