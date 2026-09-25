<?php

namespace App\Modules\Api\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API counterpart of SetTenantContext: the business comes from the
 * `X-Tenant` header (slug) instead of the session. Same rules — active,
 * not deleted, and the caller is a member — and a miss is a 404 so the API
 * cannot be used to discover which businesses exist.
 */
class ResolveApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->header('X-Tenant', '');
        $tenant = $slug !== '' ? Tenant::query()->where('slug', $slug)->first() : null;

        if (! $tenant || $tenant->status !== 'active' || $tenant->trashed() || ! $request->user()->belongsToTenant($tenant)) {
            return response()->json(['message' => 'Unknown business. Send its slug in the X-Tenant header.'], 404);
        }

        $context = app(TenantContext::class);
        $context->set($tenant);

        try {
            return $next($request);
        } finally {
            $context->forget();
        }
    }
}
