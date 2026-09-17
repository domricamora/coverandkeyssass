@php
    /** Star rating. Falls back to a "New" chip when there are no reviews yet. */
    $value = (float) ($rating ?? 0);
    $filled = $value > 0 ? (int) round($value) : 0;
@endphp

@if ($value <= 0)
    <span class="chip-inline">New listing</span>
@else
    <span class="rating" role="img" aria-label="{{ number_format($value, 1) }} out of 5 from {{ $count ?? 0 }} review(s)">
        @for ($i = 1; $i <= 5; $i++)
            <svg width="13" height="13" viewBox="0 0 20 20" fill="{{ $i <= $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.4" aria-hidden="true" style="{{ $i <= $filled ? '' : 'opacity:.35;' }}">
                <path d="M10 1.6l2.6 5.3 5.8.85-4.2 4.1 1 5.75L10 14.9l-5.2 2.7 1-5.75L1.6 7.75l5.8-.85L10 1.6z"/>
            </svg>
        @endfor
    </span>
@endif