<?php

namespace App\Support;

use App\Models\Module;
use App\Models\User;
use App\Modules\Marketplace\Models\Property;

/**
 * The dashboard sidebar, grouped by job (hotel, restaurant, guests, …) and
 * filtered by the signed-in user's permissions. One source for both shells:
 * the Blade sidebar partial and the React/Inertia shell (shared prop `nav`).
 *
 * `spa` marks screens rendered by Inertia, so the React shell can navigate
 * to them without a full page load.
 */
class DashboardNav
{
    /** Route names rendered by Inertia (React). */
    public const SPA = ['dashboard', 'frontdesk.index', 'floor.index', 'housekeeping.index', 'maintenance.index', 'bookings.index', 'properties.index', 'restaurants.index'];

    /** @return list<array{label: ?string, items: list<array{label: string, href: string, active: bool, icon: string, badge: ?int, spa: bool}>}> */
    public static function for(?User $user, string $route): array
    {
        $tenant = app(TenantContext::class);
        $can = fn (string $permission) => (bool) $user?->hasPermissionTo($permission);
        $on = fn (string ...$prefixes) => collect($prefixes)->contains(fn ($p) => $route === $p || str_starts_with($route, $p.'.'));

        $groups = [
            [null, [
                ['Overview', 'dashboard', $route === 'dashboard', 'M3 12l9-8 9 8M5 10v10h14V10', true],
            ]],
        ];

        if ($tenant->has()) {
            $workforce = Module::query()->where('slug', 'workforce')->first();
            $workforceOn = $workforce && app(ModuleService::class)->isEnabled($workforce, $tenant->tenant());
            $unread = $user?->unreadNotifications()->count() ?? 0;
            $staff = $can('staff.view');

            $groups[0][1][] = ['Notifications', 'notifications.index', $on('notifications'), 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0', true, $unread ?: null];
            $groups[0][1][] = ['Messages', 'messages.index', $on('messages'), 'M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', true];

            $groups[] = ['Hotel', [
                ['Front desk', 'frontdesk.index', $on('frontdesk'), 'M3 21h18M4 21V10h16v11M8 10V6a4 4 0 018 0v4M12 14v3', $can('bookings.view')],
                ['Bookings', 'bookings.index', $on('bookings', 'folio'), 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z', $can('bookings.view')],
                ['Housekeeping', 'housekeeping.index', $on('housekeeping'), 'M9 5h6M12 5v4M5 21l2-12h10l2 12H5z', $can('housekeeping.view')],
                ['Maintenance', 'maintenance.index', $on('maintenance'), 'M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.5-.5-.5-2.5 2.5-2.5z', $can('maintenance.view')],
                ['Rooms & rates', 'properties.index', $on('properties'), 'M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h.01M15 17h.01', (bool) $user?->can('viewAny', Property::class)],
            ]];

            $groups[] = ['Restaurant', [
                ['Restaurant floor', 'floor.index', $on('floor'), 'M4 6h16M6 6v12M18 6v12M9 18h6M9 10h6', $can('pos.use')],
                ['Restaurants', 'restaurants.index', $on('restaurants'), 'M7 3v8a2 2 0 002 2v8M5 3v5M9 3v5M17 21V3c-2 1-3 4-3 7h3', $can('restaurants.view')],
                ['Inventory', 'inventory.index', $on('inventory'), 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', $can('inventory.view')],
            ]];

            $groups[] = ['Guests', [
                ['Guests', 'crm.index', $on('crm'), 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', $can('crm.view')],
                ['Reviews', 'reviews.index', $on('reviews'), 'M8 10h8M8 14h5M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z', $can('reviews.view')],
                ['Loyalty', 'loyalty.index', $on('loyalty'), 'M12 3l2.7 5.5 6 .9-4.35 4.2 1 6-5.35-2.8-5.35 2.8 1-6L3.3 9.4l6-.9L12 3z', $can('loyalty.view')],
                ['Marketing', 'marketing.index', $on('marketing'), 'M11 5L6 9H2v6h4l5 4V5zM15.5 8.5a5 5 0 010 7M19 5a10 10 0 010 14', $can('marketing.view')],
            ]];

            $groups[] = ['Team & money', [
                [$staff ? 'Staff & rota' : 'My work', $staff ? 'staff.index' : 'my-work.index', $on('staff', 'my-work'), 'M17 20h5v-2a4 4 0 00-5-3.9M9 20H2v-2a4 4 0 015-3.9m5-4.1a4 4 0 100-8 4 4 0 000 8z', $workforceOn],
                ['Accounting', 'accounting.index', $on('accounting'), 'M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2-3-2V4a1 1 0 011-1z', $can('accounting.view')],
                ['Wallet', 'wallet.index', $on('wallet'), 'M3 7h16a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm0 0l2-3h12M16 13h.01', $can('wallet.view')],
                ['Billing', 'billing.index', $on('billing'), 'M3 7h18v10H3zM3 11h18M7 15h3', $can('billing.view')],
            ]];
        }

        $groups[] = ['Settings', [
            ['Team', 'team', $route === 'team', 'M16 14a4 4 0 10-8 0 4 4 0 008 0zM2 20c1.5-3 5-4 8-4s6.5 1 8 4', true],
            ['Business settings', 'tenants.settings', $route === 'tenants.settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM4 12a8 8 0 0116 0', true],
            ['Switch business', 'tenants.index', $route === 'tenants.index', 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', $tenant->has()],
        ]];

        return collect($groups)
            ->map(fn (array $group) => [
                'label' => $group[0],
                'items' => collect($group[1])
                    ->filter(fn (array $link) => $link[4])
                    ->map(fn (array $link) => [
                        'label' => $link[0],
                        'href' => route($link[1]),
                        'active' => $link[2],
                        'icon' => $link[3],
                        'badge' => $link[5] ?? null,
                        'spa' => in_array($link[1], self::SPA, true),
                    ])->values()->all(),
            ])
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()->all();
    }
}
