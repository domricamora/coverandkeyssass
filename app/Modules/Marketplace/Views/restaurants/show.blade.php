@php
    $r = $listing;
    $gallery = $r->galleryUrls();
    $cuisines = $r->relationLoaded('cuisines') ? $r->cuisines : collect();
    $reviews = $r->relationLoaded('reviews') ? $r->reviews : collect();
    $hours = (array) ($r->opening_hours ?? []);
@endphp

<x-public-layout :title="$title" :description="Str::limit(strip_tags($r->tagline ?: $r->description ?: $r->name), 150)">
    <div class="listing-page container">
        <a class="back-link" href="{{ route('marketplace.restaurants.index') }}">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            All restaurants
        </a>

        <div class="listing-head">
            <h1>{{ $r->name }}</h1>
            <div class="listing-head__sub">
                <span class="listing-head__rating">
                    @include('marketplace::partials.stars', ['rating' => $r->avg_rating, 'count' => $r->reviews_count])
                    @if ((float) $r->avg_rating > 0)
                        {{ number_format((float) $r->avg_rating, 1) }} ({{ $r->reviews_count }})
                    @endif
                </span>
                <span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    {{ $r->locationLabel() ?: $r->country_code }}
                </span>
                <span class="chip-inline">{{ $r->priceLevelLabel() }}</span>
            </div>
        </div>

        <div class="gallery">
            @if ($gallery !== [])
                <img class="gallery__main" src="{{ $gallery[0] }}" alt="{{ $r->name }}" decoding="async">
            @else
                <span class="gallery__main gallery__main--fallback">
                    @include('marketplace::partials.cover', ['listing' => $r, 'variant' => 'hero'])
                </span>
            @endif
        </div>

        <div class="listing-body">
            <div>
                @if ($r->tagline)
                    <p class="listing-desc" style="font-size:1.05rem;color:var(--text);">{{ $r->tagline }}</p>
                @endif

                <p class="listing-desc">{!! nl2br(e($r->description ?: 'The restaurant has not written a description yet.')) !!}</p>

                @if ($cuisines->isNotEmpty())
                    <div class="listing-block">
                        <h2>Cuisine</h2>
                        <div class="cat-row">
                            @foreach ($cuisines as $cuisine)
                                <a class="cat-chip" href="{{ route('marketplace.restaurants.index', ['cuisines' => [$cuisine->slug]]) }}">{{ $cuisine->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @php($menu = $r->relationLoaded('menuCategories') ? $r->menuCategories->filter(fn ($c) => $c->items->isNotEmpty()) : collect())
                @if ($menu->isNotEmpty())
                    <div class="listing-block">
                        <h2>Menu</h2>
                        @foreach ($menu as $category)
                            <h3 style="margin:1rem 0 .5rem;">{{ $category->name }}</h3>
                            <ul class="menu-list" style="list-style:none;padding:0;margin:0;">
                                @foreach ($category->items as $item)
                                    <li style="padding:.5rem 0;border-bottom:1px solid var(--border);{{ $item->is_available ? '' : 'opacity:.55;' }}">
                                        <div style="display:flex;justify-content:space-between;gap:1rem;">
                                            <strong>{{ $item->name }}</strong>
                                            <span>{{ $item->is_available ? $item->priceLabel() : 'Sold out' }}</span>
                                        </div>
                                        @if ($item->description)<p class="muted" style="margin:.25rem 0 0;">{{ $item->description }}</p>@endif
                                        @foreach ($item->modifierGroups->filter(fn ($g) => $g->options->isNotEmpty()) as $group)
                                            <p class="muted" style="margin:.25rem 0 0;font-size:.9rem;">
                                                {{ $group->name }}:
                                                {{ $group->options->map(fn ($o) => $o->name.((float) $o->price > 0 ? ' +'.\App\Modules\RestaurantManagement\Models\MenuItem::money((float) $o->price, $item->currency) : ''))->implode(', ') }}
                                            </p>
                                        @endforeach
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                @endif

                @if ($hours !== [])
                    <div class="listing-block">
                        <h2>Opening hours</h2>
                        <ul class="amenities">
                            @foreach ($hours as $day => $time)
                                <li>
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3M12 21a9 9 0 100-18 9 9 0 000 18z"/></svg>
                                    <span><strong>{{ Str::headline($day) }}:</strong> {{ $time }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="listing-block">
                    <h2>Guest reviews</h2>

                    @if ($reviews->isEmpty())
                        <p class="muted">No published reviews yet.</p>
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
                                        <span class="review__stars">@include('marketplace::partials.stars', ['rating' => $review->rating])</span>
                                    </div>
                                    <p class="review__body">{{ $review->comment }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div>
                <div class="card booking-widget">
                    <h3 style="margin-top:0;">Visit</h3>
                    <p class="muted" style="margin-top:0;">{{ $r->address_line ?: 'Address to be confirmed' }}</p>
                    <p class="muted">{{ $r->locationLabel() }}</p>

                    @if ($r->phone)
                        <p class="muted">Phone: {{ $r->phone }}</p>
                    @endif
                    @if ($r->email)
                        <p class="muted">Email: {{ $r->email }}</p>
                    @endif

                    <div class="price-breakdown">
                        <p><span>Table reservations</span> <strong>{{ $r->reservations_enabled ? 'Taking bookings' : 'Not enabled' }}</strong></p>
                        <p><span>Delivery</span> <strong>{{ $r->delivery_enabled ? 'Available' : 'Not enabled' }}</strong></p>
                    </div>

                    <div class="card" style="margin-top:14px;background:var(--surface-2);">
                        <p class="muted" style="margin:0;">
                            <strong>Menus, table booking and ordering arrive with the Restaurant and Ordering modules.</strong>
                            This listing already carries the profile, location and cuisine data those phases build on.
                        </p>
                    </div>

                    <div style="margin-top:14px;display:grid;gap:8px;">
                        @auth
                            <form method="POST" action="{{ $isFavorited
                                ? route('marketplace.favorites.destroy', ['restaurant', $r->id])
                                : route('marketplace.favorites.store', ['restaurant', $r->id]) }}">
                                @csrf
                                @if ($isFavorited)
                                    @method('DELETE')
                                @endif
                                <button class="btn {{ $isFavorited ? 'btn-outline' : 'btn-primary' }} btn-block" type="submit">
                                    <svg width="16" height="16" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20.5S3.8 15.4 3.8 9.6A4.6 4.6 0 0112 6.9a4.6 4.6 0 018.2 2.7c0 5.8-8.2 10.9-8.2 10.9z"/></svg>
                                    {{ $isFavorited ? 'Saved to wish list' : 'Save to wish list' }}
                                </button>
                            </form>
                        @else
                            <a class="btn btn-primary btn-block" href="{{ route('login') }}">Sign in to save this restaurant</a>
                        @endauth

                        <a class="btn btn-ghost btn-block" href="{{ route('marketplace.restaurants.index', ['location' => $r->location?->slug]) }}">More restaurants nearby</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>