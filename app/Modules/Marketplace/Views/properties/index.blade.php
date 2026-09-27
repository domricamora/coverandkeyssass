@php
    $destination ??= null;
    // Filtered result sets are near-duplicates of the base page: keep them out of the index.
    $filtered = collect(request()->except('page'))->filter(fn ($v) => filled($v))->isNotEmpty();
    $crumbs = $destination ? ['Stays' => route('marketplace.hotels'), $destination->name => null] : ['Stays' => null];
@endphp
<x-public-layout :title="$title"
    :description="$destination ? Str::limit($destination->description ?: 'Hotels, resorts and B&Bs in '.$destination->name.' with live availability and verified guest reviews.', 155) : 'Browse published stays with live filters for destination, property type, guests, price and amenities.'"
    :canonical="$destination ? route('marketplace.locations.show', $destination->slug) : route('marketplace.hotels')"
    :image="$destination ? ($properties->first()?->galleryUrls()[0] ?? null) : null"
    :breadcrumbs="$crumbs" :noindex="$filtered">
    <div class="results-page">
        <div class="filter-bar">
            <form class="filter-bar__form" method="GET" action="{{ route('marketplace.hotels') }}">
                @if (! empty($filters['location']))
                    <input type="hidden" name="location" value="{{ $filters['location'] }}">
                @endif
                <div class="filter-bar__field filter-bar__field--grow">
                    <label class="filter-bar__label" for="bar-q">Where to?</label>
                    <input class="filter-bar__input" id="bar-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by name, city or region">
                </div>
                <div class="filter-bar__divider" aria-hidden="true"></div>
                <div class="filter-bar__field filter-bar__field--sm">
                    <label class="filter-bar__label" for="bar-in">Check-in</label>
                    <input class="filter-bar__input" id="bar-in" type="date" name="check_in" min="{{ today()->toDateString() }}" value="{{ $filters['check_in'] ?? '' }}">
                </div>
                <div class="filter-bar__divider" aria-hidden="true"></div>
                <div class="filter-bar__field filter-bar__field--sm">
                    <label class="filter-bar__label" for="bar-out">Check-out</label>
                    <input class="filter-bar__input" id="bar-out" type="date" name="check_out" min="{{ today()->addDay()->toDateString() }}" value="{{ $filters['check_out'] ?? '' }}">
                </div>
                <div class="filter-bar__divider" aria-hidden="true"></div>
                <div class="filter-bar__field filter-bar__field--sm">
                    <label class="filter-bar__label" for="bar-guests">Guests</label>
                    <input class="filter-bar__input" id="bar-guests" type="number" name="guests" min="1" max="50" value="{{ $filters['guests'] ?? '' }}" placeholder="Any">
                </div>
                <div class="filter-bar__divider" aria-hidden="true"></div>
                <div class="filter-bar__field filter-bar__field--sm">
                    <label class="filter-bar__label" for="bar-sort">Sort</label>
                    <select class="filter-bar__input" id="bar-sort" name="sort">
                        <option value="recommended" @selected(($filters['sort'] ?? 'recommended') === 'recommended')>Recommended</option>
                        <option value="rating" @selected(($filters['sort'] ?? '') === 'rating')>Top rated</option>
                        <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price asc</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price desc</option>
                    </select>
                </div>
                <button class="btn btn-primary filter-bar__search" type="submit" aria-label="Search">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                </button>
            </form>
        </div>

        @php
            $chips = collect([
                'location' => $filters['location'] ?? null,
                'type' => $filters['type'] ?? null,
                'guests' => $filters['guests'] ?? null,
                'price_min' => $filters['price_min'] ?? null,
                'price_max' => $filters['price_max'] ?? null,
                'free_cancellation' => ! empty($filters['free_cancellation']) ? 'Yes' : null,
                'min_rating' => $filters['min_rating'] ?? null,
            ])->filter()->all();
            $activeAmenities = (array) ($filters['amenities'] ?? []);
        @endphp

        @if ($chips || $activeAmenities)
            <div class="filter-chips container">
                @foreach ($chips as $key => $value)
                    <a class="filter-chip" href="{{ request()->fullUrlWithQuery([$key => null]) }}" aria-label="Remove {{ $key }} filter">
                        <span>
                            @if ($key === 'price_min')
                                Min {{ \App\Support\Currency::symbol() }}{{ number_format((float) $value, 0) }}
                            @elseif ($key === 'price_max')
                                Max {{ \App\Support\Currency::symbol() }}{{ number_format((float) $value, 0) }}
                            @else
                                {{ Str::headline($key) }}: {{ $value }}
                            @endif
                        </span>
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    </a>
                @endforeach

                @foreach ($activeAmenities as $slug)
                    <a class="filter-chip" href="{{ request()->fullUrlWithQuery(['amenities' => array_values(array_diff($activeAmenities, [$slug]))]) }}" aria-label="Remove {{ $slug }} filter">
                        <span>{{ Str::headline($slug) }}</span>
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    </a>
                @endforeach

                <a class="filter-chip filter-chip--clear" href="{{ route('marketplace.hotels') }}">Clear all</a>
            </div>
        @endif

        @push('widgets') @vite('resources/js/widgets.jsx') @endpush
        <div class="container" data-widget="RecentlyViewed" data-props="{}"></div>

        <div class="results container">
            <div class="dash__side">
                @include('marketplace::partials.filters', [
                    'filters' => $filters,
                    'options' => $options,
                    'action' => route('marketplace.hotels'),
                    'mode' => 'properties',
                ])
            </div>

            <div class="results__main">
                @php
                    // Map pins: the host's exact point, else the destination's (nudged per stay so pins don't stack).
                    $mapPoints = $properties->getCollection()->map(function ($p) {
                        $exact = $p->latitude !== null && $p->longitude !== null;
                        $lat = $exact ? (float) $p->latitude : (float) $p->location?->latitude;
                        $lng = $exact ? (float) $p->longitude : (float) $p->location?->longitude;

                        return ($lat || $lng) ? [
                            'id' => $p->id, 'name' => $p->name, 'price' => $p->priceLabel(), 'exact' => $exact,
                            'url' => route('marketplace.properties.show', $p->slug), 'image' => $p->galleryUrls()[0] ?? null, 'where' => $p->locationLabel(),
                            'lat' => $exact ? $lat : $lat + (($p->id % 7) - 3) * 0.004, 'lng' => $exact ? $lng : $lng + (intdiv($p->id, 7) % 7 - 3) * 0.004,
                        ] : null;
                    })->filter()->values();
                @endphp
                <div data-widget="StayMap" data-props="{{ json_encode(['points' => $mapPoints]) }}"></div>
                <div class="results__head">
                    <h1>{{ $destination ? 'Stays in '.$destination->name : 'Stays' }}</h1>
                    <p class="results__count">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13"/></svg>
                        {{ $properties->total() }} {{ Str::plural('stay', $properties->total()) }}
                        @if (! empty($filters['check_in']) && ! empty($filters['check_out']))
                            available {{ \Carbon\Carbon::parse($filters['check_in'])->format('M j') }} – {{ \Carbon\Carbon::parse($filters['check_out'])->format('M j') }} · prices are totals for your stay
                        @else
                            match your filters · add dates to see total prices
                        @endif
                        @if (! empty($filters['q']))
                            &middot; searching &ldquo;{{ $filters['q'] }}&rdquo;
                        @endif
                    </p>
                </div>

                @if ($properties->isEmpty())
                    @include('marketplace::partials.empty', [
                        'heading' => 'No stays match those filters',
                        'message' => 'Try widening the search: remove a filter, raise the price ceiling or pick another destination.',
                        'actionLabel' => 'Reset filters',
                        'actionUrl' => route('marketplace.hotels'),
                    ])
                @else
                    <div class="grid grid-3">
                        @foreach ($properties as $property)
                            @include('marketplace::partials.property-card', ['property' => $property, 'favoriteIds' => $favoriteIds])
                        @endforeach
                    </div>

                    {{ $properties->links('marketplace::partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
</x-public-layout>