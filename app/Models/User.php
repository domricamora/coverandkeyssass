<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use \Laravel\Sanctum\HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ------------------------------------------------------------------
    // RBAC
    // ------------------------------------------------------------------

    /** All roles assigned to this user (platform and tenant-scoped). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot(['status', 'joined_at'])
            ->withTimestamps();
    }

    /** True when the user holds the platform super_admin role. */
    public function isPlatformAdmin(): bool
    {
        return $this->roles()
            ->whereNull('user_roles.tenant_id')
            ->where('roles.slug', 'super_admin')
            ->exists();
    }

    public function hasRole(string $slug, ?int $tenantId = null): bool
    {
        return $this->roles()
            ->where('roles.slug', $slug)
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('user_roles.tenant_id');

                if ($tenantId !== null) {
                    $query->orWhere('user_roles.tenant_id', $tenantId);
                }
            })
            ->exists();
    }

    /**
     * Permission check with tenant scoping.
     *
     * Platform admins hold every permission. Otherwise a permission is
     * granted only when the user has it through a role assigned in the
     * active tenant (or, explicitly, the given tenant).
     */
    public function hasPermissionTo(string $permissionName, ?int $tenantId = null): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        $tenantId ??= app(TenantContext::class)->id();

        if ($tenantId === null) {
            return false;
        }

        $roleIdCacheKey = "hoso.role.{$tenantId}.{$permissionName}";

        $roleIds = cache()->remember($roleIdCacheKey, 3600, function () use ($permissionName) {
            return \Illuminate\Support\Facades\DB::table('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('permissions.name', $permissionName)
                ->pluck('role_id')
                ->all();
        });

        if ($roleIds === []) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('user_roles')
            ->where('user_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->whereIn('role_id', $roleIds)
            ->exists();
    }

    // ------------------------------------------------------------------
    // Tenant membership
    // ------------------------------------------------------------------

    public function belongsToTenant(Tenant|int $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenants()
            ->where('tenants.id', $tenantId)
            ->where('tenant_users.status', 'active')
            ->exists();
    }

    public function activeTenantFor(string $slug): ?Tenant
    {
        return $this->tenants()
            ->where('tenants.slug', $slug)
            ->where('tenant_users.status', 'active')
            ->where('tenants.status', 'active')
            ->first();
    }

    public function tenantRole(Tenant|int $tenant): ?Role
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->roles()
            ->where('user_roles.tenant_id', $tenantId)
            ->orderBy('user_roles.id')
            ->first();
    }

    public function assignTenantRole(Tenant|int $tenant, Role $role): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $this->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $tenantId]]);
    }

    /** Assign the platform super_admin role (tenant_id = NULL). */
    public function assignPlatformRole(): void
    {
        $superAdmin = Role::query()
            ->where('slug', 'super_admin')
            ->whereNull('tenant_id')
            ->firstOrFail();

        $this->roles()->syncWithoutDetaching([$superAdmin->id => ['tenant_id' => null]]);
    }

    /** The tenant the current request operates on (from the context). */
    public function currentTenant(): ?Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}

