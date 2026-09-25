@php
    // Same grouped, permission-filtered links as the React shell (App\Support\DashboardNav).
    $current = app(\App\Support\TenantContext::class);
    $groups = \App\Support\DashboardNav::for(auth()->user(), (string) request()->route()?->getName());
@endphp

@if ($current->has())
    <p class="side-nav__business">{{ $current->tenant()->name }}</p>
@endif

@foreach ($groups as $group)
    @if ($group['label'])
        <p class="side-nav__head">{{ $group['label'] }}</p>
    @endif
    @foreach ($group['items'] as $link)
        <a class="{{ $link['active'] ? 'is-active' : '' }}" href="{{ $link['href'] }}" @if ($link['active']) aria-current="page" @endif>
            <svg class="side-nav__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
            <span>{{ $link['label'] }}</span>
            @if ($link['badge'])<span class="side-nav__badge" aria-label="{{ $link['badge'] }} unread">{{ $link['badge'] }}</span>@endif
        </a>
    @endforeach
@endforeach
