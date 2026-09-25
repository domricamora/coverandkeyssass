<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route group behind an active tenant module
 * (e.g. `module.active:property`).
 *
 * Tenant features must never be reachable when the tenant has not enabled
 * (or is no longer paying for) the owning module. Missing tenant context is
 * treated the same way SetTenantContext treats it: back to business
 * selection. An unknown or disabled module is a hard 403.
 */
class EnsureModuleActive
{
    public function __construct(private readonly ModuleService $modules) {}

    public function handle(Request $request, Closure $next, string $slug): Response
    {
        $tenant = app(TenantContext::class)->tenant();

        if (! $tenant) {
            return redirect()->route('tenants.index')
                ->with('warning', 'Please select an active business to continue.');
        }

        $module = Module::query()->where('slug', $slug)->first();

        if (! $module || ! $this->modules->isEnabled($module, $tenant)) {
            abort(403, 'The '.($module->name ?? $slug).' module is not active for this business. An owner can switch it on from Billing.');
        }

        return $next($request);
    }
}
