<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'tenant.context' => \App\Http\Middleware\SetTenantContext::class,
            'super.admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'api.tenant' => \App\Modules\Api\Middleware\ResolveApiTenant::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Phase 37: every logged error says which business and user it hit.
        $exceptions->context(fn () => array_filter([
            'tenant_id' => app(\App\Support\TenantContext::class)->id(),
            'user_id' => auth()->id(),
            'url' => app()->runningInConsole() ? null : request()->fullUrl(),
        ]));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
