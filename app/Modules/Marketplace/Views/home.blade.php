<x-public-layout :title="$title" description="Search live availability from independent hotels, resorts, B&Bs and restaurants listed on Cover & Keys.">
    <section class="hero">
        <div class="hero__bg" style="background-image: linear-gradient(135deg, #121213 0%, #1a1a1c 45%, #0a0a0b 100%);"></div>
        <div class="container hero__inner">
            <span class="pill-badge pill-badge--amber">Marketplace</span>
            <h1>Stays and tables, booked straight from the property.</h1>
            <p>
                Independent hotels, resorts, guesthouses and restaurants publish their own
                inventory here — the same data their front desk works from, priced by them.
            </p>

            <form class="hero-search" method="GET" action="{{ route('marketplace.hotels') }}">
                <div class="hero-search__field">
                    <label for="hero-location">Where</label>
                    <select class="form-input" id="hero-location" name="location">
                        <option value="">Anywhere</option>
                        @foreach ($destinations as $destination)
                            <option value="{{ $destination->slug }}">{{ $destination->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hero-search__field">
                    <label for="hero-type">Property type</label>
                    <select class="form-input" id="hero-type" name="type">
                        <option value="">Any type</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->slug }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="hero-search__field">
                    <label for="hero-guests">Guests</label>
                    <input class="form-input" id="hero-guests" type="number" name="guests" min="1" max="50" placeholder="2">
                </div>
                <div class="hero-search__field">
                    <label for="hero-price">Max price / night</label>
                    <input class="form-input" id="hero-price" type="number" name="price_max" min="0" step="500" placeholder="Any">
                </div>
                <button class="btn btn-primary" type="submit">Search stays</button>
            </form>

            <div class="hero__trust">
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Inventory published by the property
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.3 9.6A4.6 4.6 0 0112 6.9a4.6 4.6 0 018.2 2.7c0 5.8-8.2 10.9-8.2 10.9S3.8 15.4 3.8 9.6z"/></svg>
                    Save favourites to your wish list
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0v3m0-3h3m-3 0H9m3-12a9 9 0 100 18 9 9 0 000-18z"/></svg>
                    No platform booking fees
                </span>
            </div>
        </div>
    </section>

    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Guest favourites</span>
            <h2>Hand-picked stays on the platform right now</h2>
            <p>{{ $stats['properties'] }} published {{ Str::plural('property', $stats['properties']) }} across {{ $stats['destinations'] }} {{ Str::plural('destination', $stats['destinations']) }}.</p>
        </div>

        @if ($featured->isEmpty())
            <div class="empty">
                No properties are published yet. A host publishes a listing from their dashboard —
                to preview the marketplace with sample data, run
                <code>php artisan db:seed --class=MarketplaceDemoSeeder</code>.
            </div>
        @else
            <div class="grid grid-3">
                @foreach ($featured as $property)
                    @include('marketplace::partials.property-card', ['property' => $property, 'favoriteIds' => []])
                @endforeach
            </div>
            <p class="mt-4"><a class="btn btn-outline btn-sm" href="{{ route('marketplace.hotels') }}">Browse every stay</a></p>
        @endif
    </section>

    <section class="section section--raised">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Destinations</span>
                <h2>Where guests are staying</h2>
            </div>

            @if ($destinations->isEmpty())
                <div class="empty empty--sm">Destinations appear as soon as a property is published.</div>
            @else
                <div class="type-grid">
                    @foreach ($destinations as $destination)
                        <a class="type-card" href="{{ route('marketplace.locations.show', $destination->slug) }}">
                            <span class="type-card__ico" aria-hidden="true">
                                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            </span>
                            <h3>{{ $destination->name }}</h3>
                            <p>
                                {{ $destination->region ?: $destination->country }}
                                &middot; {{ $destination->properties_count }} {{ Str::plural('stay', $destination->properties_count) }}
                            </p>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($types->isNotEmpty())
                <div class="cat-row mt-4">
                    @foreach ($types as $type)
                        <a class="cat-chip" href="{{ route('marketplace.hotels', ['type' => $type->slug]) }}">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5"/></svg>
                            {{ $type->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if ($restaurants->isNotEmpty())
        <section class="container section">
            <div class="section-head">
                <span class="eyebrow">Tables</span>
                <h2>Restaurants on the platform</h2>
                <p>{{ $stats['restaurants'] }} published {{ Str::plural('restaurant', $stats['restaurants']) }}.</p>
            </div>
            <div class="grid grid-3">
                @foreach ($restaurants as $restaurant)
                    @include('marketplace::partials.restaurant-card', ['restaurant' => $restaurant, 'favoriteIds' => []])
                @endforeach
            </div>
            <p class="mt-4"><a class="btn btn-outline btn-sm" href="{{ route('marketplace.restaurants.index') }}">Browse restaurants</a></p>
        </section>
    @endif

    <section class="host-cta">
        <div class="container section host-cta__inner">
            <div>
                <h2>Run the whole property from one system</h2>
                <p>Publishing to the marketplace is one switch: the same account runs reservations, housekeeping, your team and reporting.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="btn btn-primary" href="{{ route('register') }}">Create your account</a>
                <a class="btn btn-light" href="{{ route('marketing.pricing') }}">See module pricing</a>
            </div>
        </div>
    </section>
</x-public-layout>