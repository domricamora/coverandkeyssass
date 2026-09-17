@php
    $actionLabel = $actionLabel ?? null;
    $actionUrl = $actionUrl ?? null;
@endphp

<div class="empty-state">
    <div class="empty-state__icon" aria-hidden="true">
        <svg width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
        </svg>
    </div>
    <h3>{{ $heading }}</h3>
    <p>{{ $message }}</p>
    @if ($actionLabel && $actionUrl)
        <a class="btn btn-primary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
    @endif
</div>