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
