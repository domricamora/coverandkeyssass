<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Canonical permission catalogue for Phase 01 (Foundation).
 *
 * Module phases will extend this list — new permissions belong here
 * so they are always created consistently across environments.
 */
class PermissionRegistry
{
    public static function all(): array
    {
        return [
            // group => [permission => display]
            'tenants' => [
                'tenants.view' => 'View tenants',
                'tenants.create' => 'Create tenants',
                'tenants.update' => 'Update tenants',
                'tenants.delete' => 'Delete tenants',
            ],
            'team' => [
                'team.view' => 'View team members',
                'team.manage' => 'Invite and manage team members',
            ],
            'roles' => [
                'roles.view' => 'View roles and permissions',
                'roles.manage' => 'Assign roles and permissions',
            ],
            'audit' => [
                'audit.view' => 'View audit logs',
            ],
            'platform' => [
                'platform.users.view' => 'View platform users',
                'platform.users.manage' => 'Suspend or activate platform users',
                'platform.tenants.view' => 'View all tenants',
                'platform.tenants.manage' => 'Create, suspend and delete tenants',
            ],
            'modules' => [
                'modules.view' => 'View modules',
                'modules.manage' => 'Create and edit modules',
                'modules.activate' => 'Activate modules for tenants',
                'modules.deactivate' => 'Disable modules for tenants',
            ],
            'inventory' => [
                'rooms.view' => 'View room types and rooms',
                'rooms.create' => 'Create room types and rooms',
                'rooms.update' => 'Update room types and rooms',
                'rooms.delete' => 'Delete room types and rooms',
                'rates.view' => 'View rates',
                'rates.manage' => 'Create, edit and delete rate periods',
                'availability.view' => 'View availability',
                'availability.manage' => 'Block and reopen availability',
            ],
            'properties' => [
                'properties.view' => 'View properties',
                'properties.create' => 'Create properties',
                'properties.update' => 'Update properties',
                'properties.delete' => 'Delete properties',
                'properties.publish' => 'Publish or unpublish properties',
                'properties.staff.manage' => 'Assign property staff',
            ],
            'bookings' => [
                'bookings.view' => 'View bookings and the room calendar',
                'bookings.create' => 'Create reservations and walk-ins',
                'bookings.update' => 'Confirm, check in/out, cancel and mark no-shows',
                'promotions.manage' => 'Create and deactivate promo codes',
            ],
            'wallet' => [
                'wallet.view' => 'View the host wallet, commissions and payouts',
                'payouts.request' => 'Request payouts from the host wallet',
            ],
            'restaurants' => [
                'restaurants.view' => 'View restaurant listings',
                'restaurants.create' => 'Create restaurant listings',
                'restaurants.update' => 'Update restaurant listings',
                'restaurants.delete' => 'Delete restaurant listings',
                'restaurants.publish' => 'Publish or unpublish restaurant listings',
                'menu.view' => 'View the menu',
                'menu.manage' => 'Edit menu categories, items, prices and modifiers',
                'tables.view' => 'View dining areas and tables',
                'tables.manage' => 'Edit dining areas and tables',
                'reservations.view' => 'View table reservations',
                'reservations.manage' => 'Create, confirm, seat, cancel and no-show table reservations',
                'orders.view' => 'View food orders',
                'orders.manage' => 'Accept, prepare, complete, cancel and refund food orders',
                'delivery.manage' => 'Manage delivery zones, prep time and drivers',
            ],
        ];
    }

    /** Flat list of permission names, e.g. ['tenants.view', ...]. */
    public static function names(): array
    {
        return array_merge(...array_values(array_map('array_keys', self::all())));
    }

    public static function seed(): void
    {
        foreach (self::all() as $group => $permissions) {
            foreach ($permissions as $name => $display) {
                \App\Models\Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['group' => $group, 'display_name' => $display],
                );
            }
        }

        Cache::forget('hoso.permissions.all');
    }

    /** Cached id => name map used by the permission checks. */
    public static function cachedIds(): array
    {
        return Cache::rememberForever('hoso.permissions.all', function () {
            return \App\Models\Permission::query()->pluck('id', 'name')->all();
        });
    }
}
