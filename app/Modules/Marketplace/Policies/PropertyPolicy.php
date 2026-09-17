<?php

namespace App\Modules\Marketplace\Policies;

use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use App\Support\TenantContext;

/**
 * Authorization for host-side property management (Phase 04 UI, enforced
 * from now on so nothing can slip through):
 *
 * - the actor must hold the permission inside the active tenant, and
 * - the property must belong to that same tenant.
 *
 * Cross-tenant access is denied even when the permission is held, and the
 * public marketplace never routes through this policy (it is read-only).
 * Platform super admins are granted everything by Gate::before
 * (AppServiceProvider), which is the deliberate, audited bypass.
 */
class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('properties.view');
    }

    public function view(User $user, Property $property): bool
    {
        return $this->sameTenant($property) && $user->hasPermissionTo('properties.view');
    }

    public function create(User $user): bool
    {
        return app(TenantContext::class)->has() && $user->hasPermissionTo('properties.create');
    }

    public function update(User $user, Property $property): bool
    {
        return $this->sameTenant($property) && $user->hasPermissionTo('properties.update');
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->sameTenant($property) && $user->hasPermissionTo('properties.delete');
    }

    public function publish(User $user, Property $property): bool
    {
        return $this->sameTenant($property) && $user->hasPermissionTo('properties.publish');
    }

    private function sameTenant(Property $property): bool
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId !== null && (int) $property->tenant_id === (int) $tenantId;
    }
}