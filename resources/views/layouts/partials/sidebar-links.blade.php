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
    <a class="{{ request()->routeIs('tenants.index') ? 'is-active' : '' }}" href="{{ route('tenants.index') }}">
        <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Switch business
    </a>
@endif
