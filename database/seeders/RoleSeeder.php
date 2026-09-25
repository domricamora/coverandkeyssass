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
                    'properties.view', 'properties.create', 'properties.update', 'properties.delete', 'properties.publish', 'properties.staff.manage',
                    'restaurants.view', 'restaurants.create', 'restaurants.update', 'restaurants.delete', 'restaurants.publish',
                    'menu.view', 'menu.manage', 'tables.view', 'tables.manage', 'reservations.view', 'reservations.manage', 'orders.view', 'orders.manage', 'delivery.manage',
                    'rooms.view', 'rooms.create', 'rooms.update', 'rooms.delete',
                    'rates.view', 'rates.manage',
                    'availability.view', 'availability.manage',
                    'bookings.view', 'bookings.create', 'bookings.update', 'promotions.manage',
                    'folio.view', 'folio.manage', 'folio.void',
                    'wallet.view', 'payouts.request',
                ],
            ],
            'manager' => [
                'display_name' => 'General Manager',
                'description' => 'Runs day-to-day operations and manages the team.',
                'permissions' => [
                    'tenants.view', 'tenants.update',
                    'team.view', 'team.manage',
                    'roles.view',
                    'properties.view', 'properties.create', 'properties.update', 'properties.publish', 'properties.staff.manage',
                    'restaurants.view', 'restaurants.create', 'restaurants.update', 'restaurants.publish',
                    'menu.view', 'menu.manage', 'tables.view', 'tables.manage', 'reservations.view', 'reservations.manage', 'orders.view', 'orders.manage', 'delivery.manage',
                    'rooms.view', 'rooms.create', 'rooms.update',
                    'rates.view', 'rates.manage',
                    'availability.view', 'availability.manage',
                    'bookings.view', 'bookings.create', 'bookings.update', 'promotions.manage',
                    'folio.view', 'folio.manage', 'folio.void',
                    'wallet.view',
                ],
            ],
            'front_desk' => [
                'display_name' => 'Front Desk',
                'description' => 'Handles guest-facing operations.',
                'permissions' => [
                    'team.view',
                    'properties.view',
                    'restaurants.view', 'menu.view', 'tables.view', 'reservations.view', 'reservations.manage', 'orders.view', 'orders.manage',
                    'rooms.view', 'rates.view', 'availability.view',
                    'bookings.view', 'bookings.create', 'bookings.update',
                    'folio.view', 'folio.manage',
                ],
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

        // Refresh existing tenants' system roles so new catalogue entries
        // (e.g. Phase 04 inventory permissions) reach already-provisioned
        // businesses when `php artisan db:seed` is re-run.
        foreach (\App\Models\Tenant::query()->get(['id']) as $tenant) {
            self::ensureTenantRoles($tenant->id);
        }
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
