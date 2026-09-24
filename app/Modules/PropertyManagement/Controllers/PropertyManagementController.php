<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Property;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Shared resolution + guard for host-side property resources.
 *
 * IMPORTANT: routes in this module deliberately do NOT type-hint models.
 * Laravel's SubstituteBindings middleware is priority-hoisted and runs
 * BEFORE the tenant.context middleware, so implicit binding would resolve
 * rows without a tenant scope (the same reason the marketplace module
 * resolves public lookups manually through publicQuery()).
 *
 * Instead every controller receives the raw parameter and resolves it here,
 * after the context middleware has run, always through a tenant-scoped
 * parent (property → room type → room / rates / blocks / staff). Anything
 * that does not belong to the active tenant resolves to a plain 404.
 */
abstract class PropertyManagementController extends Controller
{
    /**
     * Resolve a property by slug within the active tenant.
     * Cross-tenant slugs are a 404, never a 403, so existence is not leaked.
     */
    protected function resolveProperty(string $slug): Property
    {
        $property = Property::query()->where('slug', $slug)->firstOrFail();

        $tenantId = app(TenantContext::class)->id();

        abort_unless(
            $tenantId !== null && (int) $property->tenant_id === (int) $tenantId,
            404,
        );

        return $property;
    }

    /** 404 unless the actor holds the permission inside the active tenant. */
    protected function authorizeProperty(Request $request, Property $property, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
