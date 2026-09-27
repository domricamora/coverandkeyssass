@php
    $p = $property;
    $url = route('marketplace.properties.show', $p->slug);
    $gallery = $p->galleryUrls();
    $highlights = (array) ($p->highlights ?? []);
    $amenities = $p->relationLoaded('amenities') ? $p->amenities : collect();
    $reviews = $p->relationLoaded('reviews') ? $p->reviews : collect();
    $crumbs = ['Stays' => route('marketplace.hotels')];
    if ($p->location) {
        $crumbs[$p->location->name] = route('marketplace.locations.show', $p->location->slug);
    }
    $crumbs[$p->name] = null;
@endphp

<x-public-layout :title="$title" :description="Str::limit(strip_tags($p->tagline ?: $p->description ?: $p->name), 155)"
    :image="$gallery[0] ?? null" :canonical="$url" :breadcrumbs="$crumbs" :schema="[\App\Support\Seo::property($p)]">
    @push('widgets') @vite('resources/js/widgets.jsx') @endpush
    {{-- Remembers this stay for the "Recently viewed" strip (browser only). --}}
    <div data-widget="RecentlyViewed" data-props="{{ json_encode(['record' => ['slug' => $p->slug, 'name' => $p->name, 'url' => $url, 'image' => $gallery[0] ?? null, 'price' => $p->priceLabel(), 'where' => $p->locationLabel() ?: null]]) }}"></div>
    <div class="listing-page container">

        <div class="listing-head">
            <h1>{{ $p->name }}</h1>
            @include('platform-admin::partials.listing-trust', ['listing' => $p, 'kind' => 'properties'])
            <div class="listing-head__sub">
                <span class="listing-head__rating">
                    @include('marketplace::partials.stars', ['rating' => $p->avg_rating, 'count' => $p->reviews_count])
                    @if ((float) $p->avg_rating > 0)
                        {{ number_format((float) $p->avg_rating, 1) }} ({{ $p->reviews_count }})
                    @endif
                </span>
                <span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    {{ $p->locationLabel() ?: $p->country_code }}
                </span>
                @if ($p->propertyType)
                    <span class="kind-chip">{{ $p->propertyType->name }}</span>
                @endif
            </div>
        </div>

        <div class="gallery" x-data>
            @if ($gallery !== [])
                @include('marketplace::partials.mosaic', ['images' => $gallery, 'name' => $p->name])
            @else
                <span class="gallery__main gallery__main--fallback">
                    @include('marketplace::partials.cover', ['listing' => $p, 'variant' => 'hero'])
                </span>
                <p class="muted mt-2">The host has not uploaded photos yet — the listing renders a placeholder instead of a stock image.</p>
            @endif
        </div>

        @include('marketplace::partials.tour', ['listing' => $p])

        <div class="listing-body">
            <div>
                <div class="listing-host">
                    <span class="avatar" aria-hidden="true">{{ strtoupper(substr($p->host?->name ?? 'H', 0, 1)) }}</span>
                    <div>
                        <p class="listing-host__name">Hosted by {{ $p->host?->name ?? 'the property team' }}</p>
                        <p class="listing-host__meta">
                            {{ $p->bedrooms }} {{ Str::plural('bedroom', $p->bedrooms) }} &middot;
                            {{ $p->beds }} {{ Str::plural('bed', $p->beds) }} &middot;
                            {{ $p->bathrooms }} {{ Str::plural('bathroom', $p->bathrooms) }} &middot;
                            up to {{ $p->max_guests }} {{ Str::plural('guest', $p->max_guests) }}
                        </p>
                    </div>
                </div>

                @if ($p->tagline)
                    <p class="listing-desc" style="font-size:1.05rem;color:var(--text);">{{ $p->tagline }}</p>
                @endif

                <p class="listing-desc">{!! nl2br(e($p->description ?: 'The host has not written a description yet.')) !!}</p>

                @if ($highlights !== [])
                    <div class="listing-block">
                        <h2>What makes this stay</h2>
                        <ul class="amenities">
                            @foreach ($highlights as $highlight)
                                <li>
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    {{ $highlight }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($amenities->isNotEmpty())
                    <div class="listing-block">
                        <h2>Amenities</h2>
                        <ul class="amenities">
                            @foreach ($amenities as $amenity)
                                <li>
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    {{ $amenity->name }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="listing-block">
                    <h2>House rules &amp; times</h2>
                    <p class="muted">Check-in from {{ $p->check_in_time }} &middot; check-out by {{ $p->check_out_time }}.</p>
                    @if (! empty($p->policies))
                        <ul class="amenities">
                            @foreach (\Illuminate\Support\Arr::except((array) $p->policies, ['free_cancellation_days']) as $key => $value)
                                <li>
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01M11 12h1v4h1"/></svg>
                                    <span><strong>{{ Str::headline($key) }}:</strong> {{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="listing-block">
                    <h2>Guest reviews</h2>
                    @include('reviews::summary')

                    @if ($reviews->isEmpty())
                        <p class="muted">No published reviews yet. Guests can leave a verified review after their stay.</p>
                    @else
                        <ul class="reviews">
                            @foreach ($reviews as $review)
                                <li>
                                    <div class="review__head">
                                        <span class="avatar avatar--sm" aria-hidden="true">{{ strtoupper(substr($review->user?->name ?? 'G', 0, 1)) }}</span>
                                        <div>
                                            <p class="review__name">{{ $review->user?->name ?? 'Guest' }}</p>
                                            <p class="review__date">{{ $review->published_at?->format('M Y') ?? $review->created_at->format('M Y') }}</p>
                                        </div>
                                        <span class="review__stars">
                                            @include('marketplace::partials.stars', ['rating' => $review->rating])
                                        </span>
                                    </div>
                                    @if ($review->title)
                                        <p class="review__body"><strong>{{ $review->title }}</strong></p>
                                    @endif
                                    <p class="review__body">{{ $review->comment }}</p>

                                    @if ($review->host_response)
                                        <div class="card" style="margin-top:10px;">
                                            <p class="muted" style="margin:0;"><strong>Response from the host:</strong> {{ $review->host_response }}</p>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div>
                <div class="card booking-widget">
                    @php
                        // React booking panel (live quote → review step); the Blade below is the no-JS fallback it replaces.
                        $stayPanel = ! empty($reservableRoomTypes) && $reservableRoomTypes->isNotEmpty() ? [
                            'quoteUrl' => route('marketplace.properties.quote', $p->slug),
                            'reviewUrl' => route('stay.review', $p->slug),
                            'root' => url('/'),
                            'signedIn' => auth()->check(),
                            'currency' => $p->currency ?: 'PHP',
                            'fromPrice' => $p->priceLabel(),
                            'today' => today()->toDateString(),
                            'dates' => $stayDates,
                            'roomTypes' => $reservableRoomTypes->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'sleeps' => $t->max_guests])->values(),
                        ] : null;
                    @endphp
                    @if ($stayPanel)
                        @push('widgets') @vite('resources/js/widgets.jsx') @endpush
                        <div data-widget="StayPanel" data-props="{{ json_encode($stayPanel) }}">
                    @endif
                    @php
                        $freeCancelDays = $p->policies['free_cancellation_days'] ?? null;
                        $cancelBy = ($freeCancelDays !== null && $stayDates) ? \Carbon\Carbon::parse($stayDates['check_in'])->subDays((int) $freeCancelDays) : null;
                    @endphp

                    @if ($quote)
                        <p class="booking-widget__price">
                            {{ \App\Support\Currency::symbol($p->tenant) }}{{ number_format($quote['total'], 0) }} <span>total</span>
                        </p>
                        <div class="price-breakdown">
                            <p><span>{{ \Carbon\Carbon::parse($stayDates['check_in'])->format('D, M j') }} – {{ \Carbon\Carbon::parse($stayDates['check_out'])->format('D, M j') }}</span> <strong>{{ $quote['nights'] }} {{ Str::plural('night', $quote['nights']) }}</strong></p>
                            <p><span>{{ $quote['room_type'] }}</span> <strong>{{ \App\Support\Currency::symbol($p->tenant) }}{{ number_format($quote['per_night'], 0) }} avg / night</strong></p>
                            <p class="price-breakdown__total"><span>Total for your stay</span> <span>{{ \App\Support\Currency::symbol($p->tenant) }}{{ number_format($quote['total'], 0) }}</span></p>
                        </div>
                        @if ($quote['rooms_left'] <= 2)
                            <p class="listing-card__scarce" style="margin-top:10px;">Only {{ $quote['rooms_left'] }} {{ Str::plural('room', $quote['rooms_left']) }} left for these dates</p>
                        @endif
                    @elseif ($stayDates)
                        <p class="booking-widget__price">Not available <span>for these dates</span></p>
                        <p class="muted" style="margin:6px 0 0;">No room is free every night of your stay, or a minimum stay applies. Try other dates.</p>
                    @else
                        <p class="booking-widget__price">
                            {{ $p->priceLabel() }} <span>/ night</span>
                        </p>
                        <div class="price-breakdown">
                            <p><span>Nightly rate</span> <strong>{{ $p->priceLabel() }}</strong></p>
                            @if ($p->weekend_price)
                                <p><span>Weekend rate</span> <strong>{{ \App\Support\Currency::format($p->weekend_price, $p->tenant, 0) }}</strong></p>
                            @endif
                            <p class="price-breakdown__total"><span>Stay total</span> <span>Add dates to see it</span></p>
                        </div>
                    @endif

                    @if ($freeCancelDays !== null)
                        <p class="listing-card__perk" style="margin-top:10px;">
                            @if ($cancelBy && $cancelBy->isFuture())
                                Free cancellation until {{ $cancelBy->format('M j, Y') }}
                            @elseif (! $cancelBy)
                                Free cancellation up to {{ $freeCancelDays }} {{ Str::plural('day', (int) $freeCancelDays) }} before check-in
                            @endif
                        </p>
                    @endif

                    {{-- Check availability: reloads the page with a live quote for the dates. --}}
                    <form method="GET" action="{{ route('marketplace.properties.show', $p->slug) }}" style="margin-top:14px;display:grid;gap:8px;">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:8px;">
                            <label>Check-in <input class="form-input" type="date" name="check_in" value="{{ $stayDates['check_in'] ?? '' }}" min="{{ today()->toDateString() }}" required></label>
                            <label>Check-out <input class="form-input" type="date" name="check_out" value="{{ $stayDates['check_out'] ?? '' }}" min="{{ today()->addDay()->toDateString() }}" required></label>
                            <label>Guests <input class="form-input" type="number" name="guests" min="1" max="50" value="{{ $stayDates['guests'] ?? 2 }}"></label>
                        </div>
                        <button class="btn btn-outline btn-block" type="submit">{{ $stayDates ? 'Update dates' : 'Check availability' }}</button>
                    </form>

                    @if (! empty($reservableRoomTypes) && $reservableRoomTypes->isNotEmpty())
                        @auth
                            <form method="POST" action="{{ route('marketplace.properties.reserve', $p->slug) }}" style="margin-top:14px;display:grid;gap:8px;">
                                @csrf
                                @if ($quote)
                                    <input type="hidden" name="check_in" value="{{ $stayDates['check_in'] }}">
                                    <input type="hidden" name="check_out" value="{{ $stayDates['check_out'] }}">
                                @else
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                        <label>Check-in <input class="form-input" type="date" name="check_in" value="{{ old('check_in', $stayDates['check_in'] ?? '') }}" min="{{ today()->toDateString() }}" required></label>
                                        <label>Check-out <input class="form-input" type="date" name="check_out" value="{{ old('check_out', $stayDates['check_out'] ?? '') }}" min="{{ today()->addDay()->toDateString() }}" required></label>
                                    </div>
                                @endif
                                <label>Room
                                    <select class="form-input" name="room_type_id">
                                        @foreach ($reservableRoomTypes as $type)
                                            <option value="{{ $type->id }}" @selected((int) old('room_type_id', $quote['room_type_id'] ?? 0) === $type->id)>{{ $type->name }} · {{ $type->priceLabel() }} · sleeps {{ $type->max_guests }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
                                    <label>Rooms <input class="form-input" type="number" name="quantity" min="1" max="10" value="{{ old('quantity', 1) }}"></label>
                                    <label>Adults <input class="form-input" type="number" name="adults" min="1" value="{{ old('adults', $stayDates['guests'] ?? 2) }}"></label>
                                    <label>Children <input class="form-input" type="number" name="children" min="0" value="{{ old('children', 0) }}"></label>
                                </div>
                                <label>Promo code <input class="form-input" name="promo_code" value="{{ old('promo_code') }}"></label>
                                @foreach (['check_in', 'check_out', 'rooms', 'adults', 'promo_code', 'room_type_id'] as $field)
                                    @error($field) <p class="muted" style="color:var(--danger, #b42318);margin:0;">{{ $message }}</p> @enderror
                                @endforeach
                                <button class="btn btn-primary btn-block" type="submit">Reserve</button>
                                <p class="muted" style="margin:0;text-align:center;font-size:.85rem;">You won't be charged yet. The host confirms your request first.</p>
                            </form>
                        @else
                            <a class="btn btn-primary btn-block" style="margin-top:14px;" href="{{ route('login') }}">Sign in to book</a>
                        @endauth
                    @else
                        <div class="card" style="margin-top:14px;background:var(--surface-2);">
                            <p class="muted" style="margin:0;">This host is not taking online reservations yet.</p>
                        </div>
                    @endif
                    @if ($stayPanel)
                        </div>
                    @endif

                    <div style="margin-top:14px;display:grid;gap:8px;">
                        @auth
                            <form method="POST" action="{{ $isFavorited
                                ? route('marketplace.favorites.destroy', ['property', $p->id])
                                : route('marketplace.favorites.store', ['property', $p->id]) }}">
                                @csrf
                                @if ($isFavorited)
                                    @method('DELETE')
                                @endif
                                <button class="btn btn-outline btn-block" type="submit">
                                    <svg width="16" height="16" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20.5S3.8 15.4 3.8 9.6A4.6 4.6 0 0112 6.9a4.6 4.6 0 018.2 2.7c0 5.8-8.2 10.9-8.2 10.9z"/></svg>
                                    {{ $isFavorited ? 'Saved to wish list' : 'Save to wish list' }}
                                </button>
                            </form>
                        @else
                            <a class="btn btn-outline btn-block" href="{{ route('login') }}">Sign in to save this stay</a>
                        @endauth
                        @auth
                            <a class="btn btn-ghost btn-block" href="{{ route('account.messages.create', ['property' => $p->slug]) }}">Message the host</a>
                        @endauth

                        <a class="btn btn-ghost btn-block" href="{{ route('marketplace.hotels', ['location' => $p->location?->slug]) }}">More stays in this destination</a>
                    </div>

                    <p class="booking-widget__note">
                        Listing reference {{ Str::upper(Str::substr($p->uuid, 0, 8)) }} &middot; published {{ $p->published_at?->format('M Y') ?? 'recently' }}
                    </p>
                </div>

                @if ($similar->isNotEmpty())
                    <div style="margin-top:26px;">
                        <h2 style="font-size:1.1rem;">Similar stays</h2>
                        <div class="grid" style="grid-template-columns:1fr;gap:16px;">
                            @foreach ($similar as $other)
                                <a class="card" href="{{ route('marketplace.properties.show', $other->slug) }}" style="display:grid;grid-template-columns:96px 1fr;gap:12px;align-items:center;padding:12px;">
                                    <span class="media-frame media-frame--wide" style="aspect-ratio:4/3;border-radius:10px;">
                                        @include('marketplace::partials.cover', ['listing' => $other, 'variant' => 'thumb'])
                                    </span>
                                    <span>
                                        <strong style="display:block;color:var(--text);">{{ $other->name }}</strong>
                                        <span class="muted">{{ $other->locationLabel() }}</span>
                                        <span class="muted" style="display:block;">{{ $other->priceLabel() }} / night</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-public-layout>