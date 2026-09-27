@php
    /**
     * Marketplace filter rail. Shared by stay search and restaurant search;
     * $mode selects which filter groups are relevant.
     *
     * The form submits GET, so every filter is bookmarkable and linkable —
     * no JavaScript required for the core search flow.
     */
    $mode = $mode ?? 'properties';
    $action = $action ?? route('marketplace.hotels');
    $selectedAmenities = (array) ($filters['amenities'] ?? []);
    $selectedCuisines = (array) ($filters['cuisines'] ?? []);
    $hasFilters = collect($filters)->filter(fn ($value) => is_array($value) ? count($value) : filled($value))->isNotEmpty();
@endphp

<div x-data="{ open: false }">
<button type="button" class="btn btn-outline btn-block filter-toggle" aria-controls="filter-rail" aria-expanded="false" :aria-expanded="open.toString()" @click="open = ! open">
    Filters @if ($hasFilters)<span class="filter-bar__count">on</span>@endif
</button>
<form class="card filter-rail" id="filter-rail" :class="{ 'is-open': open }" method="GET" action="{{ $action }}">
    @foreach (['q', 'check_in', 'check_out'] as $keep)
        @if (! empty($filters[$keep]))
            <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
        @endif
    @endforeach

    <div class="filter-rail__group">
        <p class="filter-rail__title">Destination</p>
        <select class="form-input" name="location">
            <option value="">Anywhere</option>
            @foreach ($options['locations'] as $location)
                <option value="{{ $location->slug }}" @selected(($filters['location'] ?? null) === $location->slug)>
                    {{ $location->name }}@if ($location->region) , {{ $location->region }}@endif ({{ $location->properties_count }})
                </option>
            @endforeach
        </select>
    </div>

    @if ($mode === 'properties')
        <div class="filter-rail__group">
            <p class="filter-rail__title">Property type</p>
            <select class="form-input" name="type">
                <option value="">Any type</option>
                @foreach ($options['property_types'] as $type)
                    <option value="{{ $type->slug }}" @selected(($filters['type'] ?? null) === $type->slug)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-rail__group">
            <p class="filter-rail__title">Guests</p>
            <input class="form-input" type="number" name="guests" min="1" max="50" value="{{ $filters['guests'] ?? '' }}" placeholder="Any">
        </div>

        <div class="filter-rail__group">
            <p class="filter-rail__title">Booking</p>
            <label class="check">
                <input type="checkbox" name="free_cancellation" value="1" @checked(! empty($filters['free_cancellation']))>
                Free cancellation
            </label>
        </div>

        <div class="filter-rail__group">
            <p class="filter-rail__title">Guest review score</p>
            <select class="form-input" name="min_rating">
                <option value="">Any score</option>
                @foreach (['4.5' => 'Exceptional: 4.5+', '4' => 'Very good: 4+', '3.5' => 'Good: 3.5+', '3' => 'Pleasant: 3+'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) ($filters['min_rating'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-rail__group">
            <p class="filter-rail__title">Price per night ({{ $options['max_price'] > 0 ? \App\Support\Currency::symbol() : \App\Support\Currency::symbol() }})</p>
            <div class="form-grid-2" style="gap:8px;">
                <input class="form-input" type="number" name="price_min" min="0" step="100" value="{{ $filters['price_min'] ?? '' }}" placeholder="Min">
                <input class="form-input" type="number" name="price_max" min="0" step="100" value="{{ $filters['price_max'] ?? '' }}" placeholder="Max">
            </div>
        </div>

        <div class="filter-rail__group">
            <p class="filter-rail__title">Amenities</p>
            <div class="filters__amenities">
                @foreach ($options['amenities'] as $amenity)
                    <label class="check">
                        <input type="checkbox" name="amenities[]" value="{{ $amenity->slug }}" @checked(in_array($amenity->slug, $selectedAmenities, true))>
                        {{ $amenity->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @else
        <div class="filter-rail__group">
            <p class="filter-rail__title">Cuisine</p>
            <div class="filters__amenities">
                @foreach ($options['cuisines'] as $cuisine)
                    <label class="check">
                        <input type="checkbox" name="cuisines[]" value="{{ $cuisine->slug }}" @checked(in_array($cuisine->slug, $selectedCuisines, true))>
                        {{ $cuisine->name }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="filter-rail__group">
            <p class="filter-rail__title">Price level</p>
            <select class="form-input" name="price_level">
                <option value="">Any</option>
                @for ($level = 1; $level <= 4; $level++)
                    <option value="{{ $level }}" @selected((int) ($filters['price_level'] ?? 0) === $level)>{{ str_repeat(\App\Support\Currency::symbol(), $level) }}</option>
                @endfor
            </select>
        </div>
    @endif

    <div class="filter-rail__group">
        <p class="filter-rail__title">Sort by</p>
        <select class="form-input" name="sort">
            <option value="recommended" @selected(($filters['sort'] ?? 'recommended') === 'recommended')>Recommended</option>
            <option value="rating" @selected(($filters['sort'] ?? '') === 'rating')>Top rated</option>
            <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Newest</option>
            @if ($mode === 'properties')
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Price: low to high</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Price: high to low</option>
            @else
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Least expensive</option>
            @endif
        </select>
    </div>

    <button class="btn btn-primary btn-block" type="submit">Apply filters</button>

    @if ($hasFilters)
        <a class="btn btn-ghost btn-block" href="{{ $action }}">Clear all</a>
    @endif
</form>
</div>