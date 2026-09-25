<x-public-layout :title="$title" description="Restaurants listed on Cover & Keys. Filter by destination, cuisine and price level." :canonical="route('marketplace.restaurants.index')" :breadcrumbs="['Restaurants' => null]" :noindex="collect(request()->except('page'))->filter(fn ($v) => filled($v))->isNotEmpty()">
    <div class="results-page">
        <div class="filter-bar">
            <form class="filter-bar__form" method="GET" action="{{ route('marketplace.restaurants.index') }}">
                <div class="filter-bar__field filter-bar__field--grow">
                    <label class="filter-bar__label" for="bar-q">Find a restaurant</label>
                    <input class="filter-bar__input" id="bar-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, cuisine or city">
                </div>
                <div class="filter-bar__divider" aria-hidden="true"></div>
                <div class="filter-bar__field filter-bar__field--sm">
                    <label class="filter-bar__label" for="bar-price">Price</label>
                    <select class="filter-bar__input" id="bar-price" name="price_level">
                        <option value="">Any</option>
                        @for ($level = 1; $level <= 4; $level++)
                            <option value="{{ $level }}" @selected((int) ($filters['price_level'] ?? 0) === $level)>{{ str_repeat('PHP', $level) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="filter-bar__divider" aria-hidden="true"></div>
                <div class="filter-bar__field filter-bar__field--sm">
                    <label class="filter-bar__label" for="bar-sort">Sort</label>
                    <select class="filter-bar__input" id="bar-sort" name="sort">
                        <option value="recommended" @selected(($filters['sort'] ?? 'recommended') === 'recommended')>Recommended</option>
                        <option value="rating" @selected(($filters['sort'] ?? '') === 'rating')>Top rated</option>
                        <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Least expensive</option>
                    </select>
                </div>
                <button class="btn btn-primary filter-bar__search" type="submit" aria-label="Search restaurants">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                </button>
            </form>
        </div>

        <div class="results container">
            <div class="dash__side">
                @include('marketplace::partials.filters', [
                    'filters' => $filters,
                    'options' => $options,
                    'action' => route('marketplace.restaurants.index'),
                    'mode' => 'restaurants',
                ])
            </div>

            <div class="results__main">
                <div class="results__head">
                    <h1>Restaurants</h1>
                    <p class="results__count">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v8a2 2 0 002 2V3m2 0v18M4 3v6a3 3 0 003 3M17 3c-1.7 1.4-2.5 3.4-2.5 5.5 0 1.7.8 3 2.5 3.5V3z"/></svg>
                        {{ $restaurants->total() }} {{ Str::plural('restaurant', $restaurants->total()) }} published
                    </p>
                </div>

                @if ($restaurants->isEmpty())
                    @include('marketplace::partials.empty', [
                        'heading' => 'No restaurants match those filters',
                        'message' => 'Menus and table reservations arrive with the Restaurant module — published listings appear here automatically.',
                        'actionLabel' => 'Reset filters',
                        'actionUrl' => route('marketplace.restaurants.index'),
                    ])
                @else
                    <div class="grid grid-3">
                        @foreach ($restaurants as $restaurant)
                            @include('marketplace::partials.restaurant-card', ['restaurant' => $restaurant, 'favoriteIds' => $favoriteIds])
                        @endforeach
                    </div>

                    {{ $restaurants->links('marketplace::partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
</x-public-layout>