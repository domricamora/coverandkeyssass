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
            'folio' => [
                'folio.view' => 'View guest folios',
                'folio.manage' => 'Post folio charges, payments and refunds',
                'folio.void' => 'Void folio lines',
            ],
            'housekeeping' => [
                'housekeeping.view' => 'View the housekeeping board',
                'housekeeping.work' => 'Start and finish cleaning tasks, report room issues',
                'housekeeping.manage' => 'Create, assign and inspect tasks, set room status',
            ],
            'maintenance' => [
                'maintenance.view' => 'View maintenance tickets',
                'maintenance.work' => 'Open tickets, add notes and files, progress assigned tickets',
                'maintenance.manage' => 'Assign, cost and close maintenance tickets',
            ],
            'staff' => [
                'staff.view' => 'View employees, the schedule, attendance and leave',
                'staff.manage' => 'Add and edit employees, departments and positions',
                'schedules.manage' => 'Schedule and cancel shifts',
                'attendance.manage' => 'Clock staff in and out',
                'leave.approve' => 'Approve or reject leave',
            ],
            'stock' => [
                'inventory.view' => 'View stock, movements, purchase orders and recipes',
                'inventory.manage' => 'Manage items, move stock, receive deliveries and edit recipes',
                'purchasing.manage' => 'Manage suppliers and purchase orders',
            ],
            'pos' => [
                'pos.use' => 'Ring up tickets, take payments and use the kitchen display',
                'pos.discount' => 'Give manual discounts at the register',
                'pos.refund' => 'Refund register tickets',
                'pos.manage' => 'Close the register and read Z-reports',
            ],
            'accounting' => [
                'accounting.view' => 'View the books, reports, invoices and expenses',
                'accounting.manage' => 'Book expenses, issue invoices and record payments',
            ],
            'crm' => [
                'crm.view' => 'View guest profiles and segments',
                'crm.manage' => 'Edit guest profiles, tags, notes and communication logs',
            ],
            'marketing' => [
                'marketing.view' => 'View campaigns, automations and promotions',
                'marketing.manage' => 'Create and send campaigns, configure automations',
            ],
            'loyalty' => [
                'loyalty.view' => 'View loyalty members, rewards and gift cards',
                'loyalty.manage' => 'Run the programme, redeem rewards, sell and void gift cards',
            ],
            'reviews' => [
                'reviews.view' => 'Read guest reviews of the business',
                'reviews.reply' => 'Reply to and report reviews',
            ],
            'messages' => [
                'messages.view' => 'Read guest conversations',
                'messages.reply' => 'Answer guests and close conversations',
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
