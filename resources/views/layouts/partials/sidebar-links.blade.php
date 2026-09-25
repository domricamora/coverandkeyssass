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
