@php
    /**
     * Cover image for a listing, or a deterministic gradient placeholder when
     * no photo has been uploaded yet.
     *
     * The placeholder hues are derived from the slug so a listing keeps the
     * same look between requests (and between cards and detail pages).
     */
    $variant = $variant ?? 'card';
    $media = $listing->coverMedia();
    $seed = crc32((string) ($listing->slug ?? $listing->name));
    $hue = $seed % 360;
    $hue2 = ($hue + 42) % 360;
    $gradient = "linear-gradient(142deg, hsl({$hue} 27% 26%) 0%, hsl({$hue2} 33% 13%) 58%, #0a0a0b 100%)";
    $label = $listing->name;
@endphp

@if ($media)
    <img src="{{ $media->url() }}" alt="{{ $media->alt ?: $label }}" loading="lazy" decoding="async">
@else
    <span class="cover-fallback" style="background: {{ $gradient }};" role="img" aria-label="{{ $label }} — no photo uploaded yet">
        <span class="cover-fallback__inner">
            <span class="cover-fallback__mark" aria-hidden="true">
                @if ($variant === 'hero')
                    <svg width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5M9 12h.01M15 12h.01"/>
                    </svg>
                @else
                    <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5"/>
                    </svg>
                @endif
            </span>
            <span class="cover-fallback__kicker">Photo pending</span>
            <span class="cover-fallback__name">{{ $label }}</span>
        </span>
    </span>
@endif