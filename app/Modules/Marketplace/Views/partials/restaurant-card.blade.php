@php
    $favoriteIds = $favoriteIds ?? [];
    $isFavorited = in_array($restaurant->id, $favoriteIds, true);
    $url = route('marketplace.restaurants.show', $restaurant->slug);
    $cuisines = $restaurant->relationLoaded('cuisines') ? $restaurant->cuisines : collect();
@endphp

<article class="listing-card">
    <a class="listing-card__media" href="{{ $url }}" aria-label="{{ $restaurant->name }}">
        @include('marketplace::partials.cover', ['listing' => $restaurant, 'variant' => 'card'])

        @if ($restaurant->isSponsored())
            <span class="listing-card__flag">Sponsored</span>
        @elseif ($restaurant->isFeaturedNow())
            <span class="listing-card__flag">Guest favourite</span>
        @endif

        <span class="listing-card__kind">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v8a2 2 0 002 2h0V3m2 0v18M4 3v6a3 3 0 003 3M17 3c-1.7 1.4-2.5 3.4-2.5 5.5 0 1.7.8 3 2.5 3.5V3z"/></svg>
            {{ $cuisines->first()->name ?? 'Restaurant' }}
        </span>
    </a>

    @auth
        <form method="POST" action="{{ $isFavorited
            ? route('marketplace.favorites.destroy', ['restaurant', $restaurant->id])
            : route('marketplace.favorites.store', ['restaurant', $restaurant->id]) }}">
            @csrf
            @if ($isFavorited)
                @method('DELETE')
            @endif
            <button class="wishlist-btn {{ $isFavorited ? 'is-active' : '' }}" type="submit"
                    aria-label="{{ $isFavorited ? 'Remove from wish list' : 'Save to wish list' }}" aria-pressed="{{ $isFavorited ? 'true' : 'false' }}">
                <svg width="17" height="17" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.5S3.8 15.4 3.8 9.6A4.6 4.6 0 0112 6.9a4.6 4.6 0 018.2 2.7c0 5.8-8.2 10.9-8.2 10.9z"/>
                </svg>
            </button>
        </form>
    @endauth

    <div class="listing-card__body">
        <div class="listing-card__row">
            <h3 class="listing-card__title"><a href="{{ $url }}">{{ $restaurant->name }}</a></h3>
            <span class="listing-card__rating">
                @include('marketplace::partials.stars', ['rating' => $restaurant->avg_rating, 'count' => $restaurant->reviews_count])
                @if ((float) $restaurant->avg_rating > 0)
                    {{ number_format((float) $restaurant->avg_rating, 1) }}
                @endif
            </span>
        </div>

        <p class="listing-card__loc">{{ $restaurant->locationLabel() ?: $restaurant->country_code }}</p>
        <p class="listing-card__meta">
            {{ $cuisines->pluck('name')->take(3)->implode(' · ') ?: 'Cuisine to be confirmed' }}
            &nbsp;|&nbsp; {{ $restaurant->priceLevelLabel() }}
        </p>
        <p class="listing-card__price">
            @if ($restaurant->reservations_enabled)
                Table reservations open
            @else
                Walk-ins welcome
            @endif
        </p>
    </div>
</article>