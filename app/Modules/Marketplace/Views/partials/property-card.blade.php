@php
    $favoriteIds = $favoriteIds ?? [];
    $isFavorited = in_array($property->id, $favoriteIds, true);
    $url = route('marketplace.properties.show', $property->slug);
@endphp

<article class="listing-card">
    <a class="listing-card__media" href="{{ $url }}" aria-label="{{ $property->name }}">
        @include('marketplace::partials.cover', ['listing' => $property, 'variant' => 'card'])

        @if ($property->is_featured)
            <span class="listing-card__flag">Guest favourite</span>
        @endif

        @if ($property->propertyType)
            <span class="listing-card__kind">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5"/></svg>
                {{ $property->propertyType->name }}
            </span>
        @endif
    </a>

    @auth
        <form method="POST" action="{{ $isFavorited
            ? route('marketplace.favorites.destroy', ['property', $property->id])
            : route('marketplace.favorites.store', ['property', $property->id]) }}">
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
            <h3 class="listing-card__title"><a href="{{ $url }}">{{ $property->name }}</a></h3>
            <span class="listing-card__rating">
                @include('marketplace::partials.stars', ['rating' => $property->avg_rating, 'count' => $property->reviews_count])
                @if ((float) $property->avg_rating > 0)
                    {{ number_format((float) $property->avg_rating, 1) }}
                @endif
            </span>
        </div>

        <p class="listing-card__loc">{{ $property->locationLabel() ?: $property->country_code }}</p>
        <p class="listing-card__meta">
            {{ $property->bedrooms }} {{ Str::plural('bedroom', $property->bedrooms) }} &middot;
            {{ $property->max_guests }} {{ Str::plural('guest', $property->max_guests) }}
        </p>
        <p class="listing-card__price">{{ $property->priceLabel() }} <span>/ night</span></p>
    </div>
</article>