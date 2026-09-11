<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformAdmin() || $user->belongsToTenant($tenant);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenant)
            && $user->hasPermissionTo('tenants.update', $tenant->id);
    }

    public function manageTeam(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenant)
            && $user->hasPermissionTo('team.manage', $tenant->id);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformAdmin();
    }
}
