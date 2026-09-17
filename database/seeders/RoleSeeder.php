<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform super_admin role and the per-tenant system roles.
 *
 * Tenant roles are created lazily for each tenant as well (see
 * Role::systemRolesForTenant) so new tenants always have sane defaults.
 */
class RoleSeeder extends Seeder
{
    public static function tenantRoleMap(): array
    {
        return [
            'owner' => [
                'display_name' => 'Owner',
                'description' => 'Full control of the business account.',
                'permissions' => [
                    'tenants.view', 'tenants.update',
                    'team.view', 'team.manage',
                    'roles.view', 'roles.manage',
                    'audit.view',
                    'properties.view', 'properties.create', 'properties.update', 'properties.delete', 'properties.publish',
                    'restaurants.view', 'restaurants.create', 'restaurants.update', 'restaurants.delete', 'restaurants.publish',
                ],
            ],
            'manager' => [
                'display_name' => 'General Manager',
                'description' => 'Runs day-to-day operations and manages the team.',
                'permissions' => [
                    'tenants.view', 'tenants.update',
                    'team.view', 'team.manage',
                    'roles.view',
                    'properties.view', 'properties.create', 'properties.update', 'properties.publish',
                    'restaurants.view', 'restaurants.create', 'restaurants.update', 'restaurants.publish',
                ],
            ],
            'front_desk' => [
                'display_name' => 'Front Desk',
                'description' => 'Handles guest-facing operations.',
                'permissions' => ['team.view', 'properties.view', 'restaurants.view'],
            ],
            'staff' => [
                'display_name' => 'Staff',
                'description' => 'General staff member.',
                'permissions' => [],
            ],
        ];
    }

    public function run(): void
    {
        // Platform role.
        $superAdmin = Role::query()->updateOrCreate(
            ['slug' => 'super_admin', 'tenant_id' => null],
            [
                'name' => 'super_admin',
                'display_name' => 'Super Admin',
                'description' => 'Platform administrator with full access.',
                'is_system' => true,
            ],
        );

        $superAdmin->syncPermissions(Permission::query()->pluck('name')->all());
    }

    public static function ensureTenantRoles(int $tenantId): void
    {
        foreach (self::tenantRoleMap() as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug, 'tenant_id' => $tenantId],
                [
                    'name' => $slug,
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            $role->syncPermissions($definition['permissions']);
        }
    }
}
