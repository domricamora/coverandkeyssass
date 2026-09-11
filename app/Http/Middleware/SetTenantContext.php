<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant from the session, validates that the
 * authenticated user is an active member of it, and initialises the
 * TenantContext used by the BelongsToTenant scope.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $tenantId = Session::get('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        // A missing, suspended or non-membered tenant must never grant context.
        if (! $tenant
            || $tenant->status !== 'active'
            || $tenant->trashed()
            || ! $user->belongsToTenant($tenant)
        ) {
            Session::forget('tenant_id');
            $context->forget();

            return redirect()->route('tenants.index')
                ->with('warning', 'Please select an active business to continue.');
        }

        $context->set($tenant);

        return $next($request);
    }
}
