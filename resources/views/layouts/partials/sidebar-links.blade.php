@php
    $current = app(\App\Support\TenantContext::class);
    $route = request()->route()?->getName();
@endphp

@php($links = [
    ['dashboard', 'Overview', 'M3 12l9-8 9 8M5 10v10h14V10'],
    ['team', 'Team', 'M16 14a4 4 0 10-8 0 4 4 0 008 0zM2 20c1.5-3 5-4 8-4s6.5 1 8 4'],
    ['tenants.settings', 'Settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM4 12a8 8 0 0116 0'],
])

@foreach ($links as [$name, $label, $path])
    <a class="{{ $route === $name ? 'is-active' : '' }}" href="{{ route($name) }}">
        <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
        {{ $label }}
    </a>
@endforeach

@if ($current->has())
    <p class="side-nav__head">{{ $current->tenant()->name }}</p>
    @if (auth()->user()?->can('viewAny', \App\Modules\Marketplace\Models\Property::class))
        <a class="{{ $route === 'properties.index' || str_starts_with((string) $route, 'properties.') ? 'is-active' : '' }}" href="{{ route('properties.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M15 9h.01M9 13h.01M15 13h.01M9 17h.01M15 17h.01"/></svg>
            Properties
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('restaurants.view'))
        <a class="{{ str_starts_with((string) $route, 'restaurants.') ? 'is-active' : '' }}" href="{{ route('restaurants.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v8a2 2 0 002 2v8M5 3v5M9 3v5M17 21V3c-2 1-3 4-3 7h3"/></svg>
            Restaurants
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('bookings.view'))
        <a class="{{ str_starts_with((string) $route, 'bookings.') ? 'is-active' : '' }}" href="{{ route('bookings.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
            Bookings
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('housekeeping.view'))
        <a class="{{ str_starts_with((string) $route, 'housekeeping.') ? 'is-active' : '' }}" href="{{ route('housekeeping.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6M12 5v4M5 21l2-12h10l2 12H5z"/></svg>
            Housekeeping
        </a>
    @endif
    @php($workforceModule = \App\Models\Module::query()->where('slug', 'workforce')->first())
    @if ($workforceModule && app(\App\Support\ModuleService::class)->isEnabled($workforceModule, $current->tenant()))
        <a class="{{ str_starts_with((string) $route, 'staff.') || str_starts_with((string) $route, 'my-work.') ? 'is-active' : '' }}" href="{{ route(auth()->user()?->hasPermissionTo('staff.view') ? 'staff.index' : 'my-work.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.9M9 20H2v-2a4 4 0 015-3.9m5-4.1a4 4 0 100-8 4 4 0 000 8z"/></svg>
            {{ auth()->user()?->hasPermissionTo('staff.view') ? 'Staff' : 'My work' }}
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('maintenance.view'))
        <a class="{{ str_starts_with((string) $route, 'maintenance.') ? 'is-active' : '' }}" href="{{ route('maintenance.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.5-.5-.5-2.5 2.5-2.5z"/></svg>
            Maintenance
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('inventory.view'))
        <a class="{{ str_starts_with((string) $route, 'inventory.') ? 'is-active' : '' }}" href="{{ route('inventory.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Inventory
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('crm.view'))
        <a class="{{ str_starts_with((string) $route, 'crm.') ? 'is-active' : '' }}" href="{{ route('crm.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Guests
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('loyalty.view'))
        <a class="{{ str_starts_with((string) $route, 'loyalty.') ? 'is-active' : '' }}" href="{{ route('loyalty.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.7 5.5 6 .9-4.35 4.2 1 6-5.35-2.8-5.35 2.8 1-6L3.3 9.4l6-.9L12 3z"/></svg>
            Loyalty
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('marketing.view'))
        <a class="{{ str_starts_with((string) $route, 'marketing.') ? 'is-active' : '' }}" href="{{ route('marketing.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5zM15.5 8.5a5 5 0 010 7M19 5a10 10 0 010 14"/></svg>
            Marketing
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('accounting.view'))
        <a class="{{ str_starts_with((string) $route, 'accounting.') ? 'is-active' : '' }}" href="{{ route('accounting.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2-3-2V4a1 1 0 011-1z"/></svg>
            Accounting
        </a>
    @endif
    @if (auth()->user()?->hasPermissionTo('wallet.view'))
        <a class="{{ str_starts_with((string) $route, 'wallet.') ? 'is-active' : '' }}" href="{{ route('wallet.index') }}">
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h16a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm0 0l2-3h12M16 13h.01"/></svg>
            Wallet
        </a>
    @endif
    <a class="{{ request()->routeIs('tenants.index') ? 'is-active' : '' }}" href="{{ route('tenants.index') }}">
        <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Switch business
    </a>
@endif
