@php
    $current = app(\App\Support\TenantContext::class);
    $route = (string) request()->route()?->getName();
    $user = auth()->user();
    $on = fn (string ...$prefixes) => collect($prefixes)->contains(fn ($p) => $route === $p || str_starts_with($route, $p.'.'));

    // Each link: [label, route, active?, icon path, visible?, badge]
    $sections = [
        '_top' => [
            ['Overview', 'dashboard', $route === 'dashboard', 'M3 12l9-8 9 8M5 10v10h14V10', true],
            ['Team', 'team', $route === 'team', 'M16 14a4 4 0 10-8 0 4 4 0 008 0zM2 20c1.5-3 5-4 8-4s6.5 1 8 4', true],
            ['Settings', 'tenants.settings', $route === 'tenants.settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM4 12a8 8 0 0116 0', true],
        ],
    ];

    if ($current->has()) {
        $workforce = \App\Models\Module::query()->where('slug', 'workforce')->first();
        $workforceOn = $workforce && app(\App\Support\ModuleService::class)->isEnabled($workforce, $current->tenant());
        $unread = $user?->unreadNotifications()->count() ?? 0;

        $sections['_top'][] = ['Notifications', 'notifications.index', $on('notifications'), 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0', true, $unread ?: null];
        $sections['_top'][] = ['Messages', 'messages.index', $on('messages'), 'M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', true];

        $sections['Front of house'] = [
            ['Properties', 'properties.index', $on('properties'), 'M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h.01M15 17h.01', (bool) $user?->can('viewAny', \App\Modules\Marketplace\Models\Property::class)],
            ['Bookings', 'bookings.index', $on('bookings'), 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z', (bool) $user?->hasPermissionTo('bookings.view')],
            ['Restaurants', 'restaurants.index', $on('restaurants'), 'M7 3v8a2 2 0 002 2v8M5 3v5M9 3v5M17 21V3c-2 1-3 4-3 7h3', (bool) $user?->hasPermissionTo('restaurants.view')],
            ['Reviews', 'reviews.index', $on('reviews'), 'M8 10h8M8 14h5M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z', (bool) $user?->hasPermissionTo('reviews.view')],
        ];

        $sections['Operations'] = [
            ['Housekeeping', 'housekeeping.index', $on('housekeeping'), 'M9 5h6M12 5v4M5 21l2-12h10l2 12H5z', (bool) $user?->hasPermissionTo('housekeeping.view')],
            ['Maintenance', 'maintenance.index', $on('maintenance'), 'M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.5-.5-.5-2.5 2.5-2.5z', (bool) $user?->hasPermissionTo('maintenance.view')],
            [$user?->hasPermissionTo('staff.view') ? 'Staff' : 'My work', $user?->hasPermissionTo('staff.view') ? 'staff.index' : 'my-work.index', $on('staff', 'my-work'), 'M17 20h5v-2a4 4 0 00-5-3.9M9 20H2v-2a4 4 0 015-3.9m5-4.1a4 4 0 100-8 4 4 0 000 8z', $workforceOn],
            ['Inventory', 'inventory.index', $on('inventory'), 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', (bool) $user?->hasPermissionTo('inventory.view')],
        ];

        $sections['Guests'] = [
            ['Guests', 'crm.index', $on('crm'), 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', (bool) $user?->hasPermissionTo('crm.view')],
            ['Loyalty', 'loyalty.index', $on('loyalty'), 'M12 3l2.7 5.5 6 .9-4.35 4.2 1 6-5.35-2.8-5.35 2.8 1-6L3.3 9.4l6-.9L12 3z', (bool) $user?->hasPermissionTo('loyalty.view')],
            ['Marketing', 'marketing.index', $on('marketing'), 'M11 5L6 9H2v6h4l5 4V5zM15.5 8.5a5 5 0 010 7M19 5a10 10 0 010 14', (bool) $user?->hasPermissionTo('marketing.view')],
        ];

        $sections['Finance'] = [
            ['Accounting', 'accounting.index', $on('accounting'), 'M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2-3-2V4a1 1 0 011-1z', (bool) $user?->hasPermissionTo('accounting.view')],
            ['Wallet', 'wallet.index', $on('wallet'), 'M3 7h16a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm0 0l2-3h12M16 13h.01', (bool) $user?->hasPermissionTo('wallet.view')],
            ['Billing', 'billing.index', $on('billing'), 'M3 7h18v10H3zM3 11h18M7 15h3', (bool) $user?->hasPermissionTo('billing.view')],
        ];

        $sections['_end'] = [
            ['Switch business', 'tenants.index', request()->routeIs('tenants.index'), 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', true],
        ];
    }
@endphp

@if ($current->has())
    <p class="side-nav__business">{{ $current->tenant()->name }}</p>
@endif

@foreach ($sections as $heading => $links)
    @php($links = array_filter($links, fn ($link) => $link[4]))
    @continue($links === [])
    @if (! str_starts_with($heading, '_'))
        <p class="side-nav__head">{{ $heading }}</p>
    @elseif ($heading === '_end')
        <hr class="side-nav__rule">
    @endif
    @foreach ($links as $link)
        <a class="{{ $link[2] ? 'is-active' : '' }}" href="{{ route($link[1]) }}" @if ($link[2]) aria-current="page" @endif>
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link[3] }}"/></svg>
            <span>{{ $link[0] }}</span>
            @if (! empty($link[5]))<span class="side-nav__badge" aria-label="{{ $link[5] }} unread">{{ $link[5] }}</span>@endif
        </a>
    @endforeach
@endforeach
