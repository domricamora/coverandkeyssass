<x-public-layout :title="$title" description="Book beach resorts, villas, B&Bs and city hotels in Boracay, El Nido, Siargao, Cebu and Baguio direct with the hosts, and reserve restaurant tables. Pay by card, GCash or Maya."
    :schema="[
        \App\Support\Seo::organization(),
        \App\Support\Seo::website(),
        \App\Support\Seo::itemList('Featured stays in the Philippines', $featured->map(fn ($p) => route('marketplace.properties.show', $p->slug))),
        \App\Support\Seo::faq($faqs),
    ]" :show-search="false">
    @php
        $pad = 'px-5 lg:px-10 2xl:px-16';
    @endphp

    {{-- Hero: full-bleed resort video, guest search first. --}}
    <section class="relative isolate flex min-h-[600px] items-end overflow-hidden bg-[#0e3b3a] lg:min-h-[min(86vh,860px)]">
        <img src="{{ asset('media/hero-resort.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover" fetchpriority="high">
        {{-- Pexels 4069480 (free licence); attached only on wide screens without reduced motion. --}}
        <video class="absolute inset-0 -z-20 h-full w-full object-cover" muted loop playsinline preload="none" poster="{{ asset('media/hero-resort.jpg') }}" data-src="{{ asset('media/hero-resort.mp4') }}" aria-hidden="true"></video>
        <script>
            (function () {
                var v = document.currentScript.previousElementSibling;
                if (!v || !window.matchMedia('(min-width: 768px) and (prefers-reduced-motion: no-preference)').matches) return;
                v.src = v.dataset.src;
                v.play().catch(function () {});
            })();
        </script>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-[#0a2426]/80 via-[#0a2426]/25 to-transparent" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-[#0a2426]/35 lg:hidden" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#0a2426]/55 via-[#0a2426]/15 to-transparent" aria-hidden="true"></div>

        <div class="w-full {{ $pad }} pb-12 pt-32 lg:pb-16">
            <p class="text-[13px] font-medium uppercase tracking-[0.18em] text-white/85">Stays &amp; tables across the islands</p>
            <h1 class="mt-4 max-w-4xl font-display text-[clamp(2.6rem,6vw,5.2rem)] font-medium leading-[1.02] tracking-[-0.02em] text-white">Wake up somewhere you'll want to tell people about.</h1>
            <p class="mt-5 max-w-xl text-[17px] leading-relaxed text-white/90">Beachfront suites, garden villas and city lofts, plus the restaurants worth the trip. Booked direct with the people who run them.</p>

            <form method="GET" action="{{ route('marketplace.hotels') }}" role="search" class="mt-9 grid max-w-4xl gap-px bg-line shadow-[0_30px_60px_-30px_rgba(0,0,0,.6)] sm:grid-cols-[1.6fr_1fr_1fr_auto]">
                <label class="bg-white px-5 py-3.5">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.1em] text-fg-3">Where to?</span>
                    <input name="q" list="home-destinations" placeholder="Boracay, El Nido, Siargao…" class="mt-0.5 w-full border-0 bg-transparent p-0 text-[15px] text-fg placeholder:text-fg-4 focus:ring-0">
                    <datalist id="home-destinations">
                        @foreach ($destinations as $destination)<option value="{{ $destination->name }}">@endforeach
                    </datalist>
                </label>
                <label class="bg-white px-5 py-3.5">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.1em] text-fg-3">Stay type</span>
                    <select name="type" class="mt-0.5 w-full border-0 bg-transparent p-0 text-[15px] text-fg focus:ring-0">
                        <option value="">Any</option>
                        @foreach ($types as $type)<option value="{{ $type->slug }}">{{ $type->name }}</option>@endforeach
                    </select>
                </label>
                <label class="bg-white px-5 py-3.5">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.1em] text-fg-3">Guests</span>
                    <input type="number" name="guests" min="1" max="50" placeholder="2 guests" class="mt-0.5 w-full border-0 bg-transparent p-0 text-[15px] text-fg placeholder:text-fg-4 focus:ring-0">
                </label>
                <button type="submit" class="flex items-center justify-center gap-2 bg-coral px-8 py-4 text-[15px] font-semibold text-white transition-colors hover:bg-coral-deep active:scale-[0.99]">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    Search
                </button>
            </form>

            <ul class="mt-6 flex flex-wrap gap-x-7 gap-y-2 text-[14px] text-white/90">
                @foreach (['Book direct, no middle-man mark-up', 'Pay by card, GCash or Maya', 'Reviews only from real stays'] as $point)
                    <li class="flex items-center gap-2">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="text-[#7fd8cf]"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Destinations --}}
    @if ($destinations->isNotEmpty())
        <section class="{{ $pad }} pt-20">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Where to next</p>
                    <h2 class="mt-2 font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Popular destinations in the Philippines</h2>
                </div>
                <a href="{{ route('marketplace.hotels') }}" class="text-[15px] font-medium text-brand hover:text-brand-deep">All destinations &rarr;</a>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-[repeat(var(--cols),minmax(0,1fr))]" style="--cols: {{ max(1, min(6, $destinations->count())) }}">
                @foreach ($destinations as $destination)
                    <a href="{{ route('marketplace.locations.show', $destination->slug) }}" class="group relative block aspect-[3/4] overflow-hidden bg-soft">
                        @if ($destination->cover)
                            <img src="{{ $destination->cover }}" alt="{{ $destination->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.06]">
                        @endif
                        <span class="absolute inset-0 bg-gradient-to-t from-[#0a2426]/75 via-transparent to-transparent" aria-hidden="true"></span>
                        <span class="absolute inset-x-4 bottom-4 text-white">
                            <span class="block font-display text-[20px] font-medium">{{ $destination->name }}</span>
                            <span class="text-[13px] text-white/85">{{ $destination->properties_count }} {{ \Illuminate\Support\Str::plural('stay', $destination->properties_count) }}@if ($destination->restaurants_count) · {{ $destination->restaurants_count }} {{ \Illuminate\Support\Str::plural('restaurant', $destination->restaurants_count) }}@endif</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Stays --}}
    <section class="{{ $pad }} pt-20">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Stays</p>
                <h2 class="mt-2 font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Top-rated hotels, resorts and villas</h2>
            </div>
            <a href="{{ route('marketplace.hotels') }}" class="text-[15px] font-medium text-brand hover:text-brand-deep">Browse all stays &rarr;</a>
        </div>

        @if ($types->isNotEmpty())
            <div class="mt-6 flex gap-2 overflow-x-auto pb-1">
                @foreach ($types as $type)
                    <a href="{{ route('marketplace.hotels', ['type' => $type->slug]) }}" class="flex h-10 shrink-0 items-center border border-line bg-white px-4 text-[14px] text-fg-2 transition-colors hover:border-brand hover:text-brand">{{ $type->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($featured->isEmpty())
            <p class="mt-8 border border-dashed border-line-strong bg-white p-8 text-center text-fg-3">No stays are published yet.</p>
        @else
            <div class="mt-8 grid gap-x-5 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $property)
                    @php($photo = $property->galleryUrls()[0] ?? null)
                    <a href="{{ route('marketplace.properties.show', $property->slug) }}" class="group block">
                        <span class="relative block aspect-[4/3] overflow-hidden bg-soft">
                            @if ($photo)<img src="{{ $photo }}" alt="{{ $property->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.04]">@endif
                            @if ($property->isFeaturedNow())
                                <span class="absolute left-3 top-3 bg-white px-2.5 py-1 text-[12px] font-semibold text-fg">Guest favourite</span>
                            @endif
                        </span>
                        <span class="mt-3.5 flex items-start justify-between gap-3">
                            <span class="min-w-0">
                                <span class="block truncate text-[16px] font-semibold text-fg">{{ $property->name }}</span>
                                <span class="block text-[14px] text-fg-3">{{ $property->locationLabel() }} · {{ $property->propertyType?->name }}</span>
                            </span>
                            @if ((float) $property->avg_rating > 0)
                                <span class="flex shrink-0 items-center gap-1 text-[14px] text-fg">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="var(--coral)" aria-hidden="true"><path d="M12 3l2.7 5.5 6 .9-4.35 4.2 1 6-5.35-2.8-5.35 2.8 1-6L3.3 9.4l6-.9z"/></svg>
                                    {{ number_format((float) $property->avg_rating, 1) }}
                                    <span class="text-fg-3">({{ $property->reviews_count }})</span>
                                </span>
                            @endif
                        </span>
                        <span class="mt-1.5 block text-[15px] text-fg"><strong class="font-semibold">{{ $property->priceLabel() }}</strong> <span class="text-fg-3">night · sleeps {{ $property->max_guests }}</span></span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Restaurants --}}
    @if ($restaurants->isNotEmpty())
        <section class="{{ $pad }} pt-24">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Eat well</p>
                    <h2 class="mt-2 font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Restaurants: reserve a table or order online</h2>
                </div>
                <a href="{{ route('marketplace.restaurants.index') }}" class="text-[15px] font-medium text-brand hover:text-brand-deep">All restaurants &rarr;</a>
            </div>
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                @foreach ($restaurants as $restaurant)
                    @php($photo = $restaurant->galleryUrls()[0] ?? null)
                    <a href="{{ route('marketplace.restaurants.show', $restaurant->slug) }}" class="group relative block aspect-[4/5] overflow-hidden bg-soft md:aspect-[3/4] xl:aspect-[4/5]">
                        @if ($photo)<img src="{{ $photo }}" alt="{{ $restaurant->name }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.05]">@endif
                        <span class="absolute inset-0 bg-gradient-to-t from-[#0a2426]/85 via-[#0a2426]/10 to-transparent" aria-hidden="true"></span>
                        <span class="absolute inset-x-5 bottom-5 text-white">
                            <span class="block text-[13px] text-white/80">{{ $restaurant->cuisines->pluck('name')->take(2)->join(' · ') }} · {{ $restaurant->priceLevelLabel() }}</span>
                            <span class="mt-1 block font-display text-[24px] font-medium leading-tight">{{ $restaurant->name }}</span>
                            <span class="mt-3 inline-flex h-9 items-center bg-white px-3.5 text-[13px] font-semibold text-fg">{{ $restaurant->reservations_enabled ? 'Reserve a table' : 'Order online' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Why book here --}}
    <section class="{{ $pad }} pt-24">
        <div class="grid gap-px border border-line bg-line sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Book direct', 'Your booking goes straight to the property’s own front desk. No reseller in between, no surprise mark-up.', 'M3 12l9-8 9 8M5 10v10h14V10'],
                ['Confirmation you can trust', 'Rooms come from the same calendar the hotel runs on, so a confirmed stay is a real room with your name on it.', 'M5 12.5l4.5 4.5L19 7.5'],
                ['Pay your way', 'Card, GCash or Maya online, with the bill itemised night by night. Everything else is settled at the desk.', 'M3 7h18v10H3zM3 11h18M7 15h3'],
                ['Reviews from real stays', 'Only guests who checked out can review, and hosts reply in public.', 'M8 10h8M8 14h5M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z'],
            ] as [$heading, $body, $icon])
                <div class="bg-white p-7">
                    <span class="flex h-11 w-11 items-center justify-center bg-brand-soft text-brand">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <h3 class="mt-5 text-[17px] font-semibold text-fg">{{ $heading }}</h3>
                    <p class="mt-2 text-[15px] leading-relaxed text-fg-2">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- For hosts --}}
    <section class="mt-24 grid bg-brand lg:grid-cols-2">
        <div class="relative min-h-[360px] overflow-hidden">
            <img src="{{ asset('img/demo/tour/lobby-1-kfnWOD1Tbp8.jpg') }}" alt="A hotel reception desk ready for arrivals" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
        </div>
        <div class="px-6 py-16 text-white sm:px-12 lg:px-16 lg:py-24">
            <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-[#9fe3db]">For hotels, resorts and restaurants</p>
            <h2 class="mt-3 max-w-xl font-display text-[clamp(1.9rem,3.2vw,2.9rem)] font-medium leading-[1.08] tracking-[-0.015em]">Hotel and restaurant management software, built into the marketplace guests book on.</h2>
            <ul class="mt-8 grid max-w-xl gap-4 text-[15px] leading-relaxed text-white/90 sm:grid-cols-2">
                @foreach ([
                    'Front desk with a drag-and-drop room chart',
                    'Housekeeping board your team runs from their phones',
                    'Restaurant register, kitchen tickets and table bookings',
                    'Folios, payments and accounting that balance themselves',
                    'Staff rota, clock-ins and leave',
                    'Guest profiles, loyalty and campaigns',
                ] as $item)
                    <li class="flex gap-3">
                        <svg width="18" height="18" fill="none" stroke="#9fe3db" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
            <div class="mt-10 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="inline-flex h-12 items-center bg-coral px-6 text-[15px] font-semibold text-white transition-colors hover:bg-coral-deep">List your property</a>
                <a href="{{ route('marketing.features') }}" class="inline-flex h-12 items-center border border-white/40 px-6 text-[15px] font-medium text-white transition-colors hover:border-white">See how it works</a>
            </div>
            <dl class="mt-12 grid max-w-md grid-cols-3 gap-6 border-t border-white/20 pt-8">
                <div><dt class="text-[13px] text-white/75">Stays listed</dt><dd class="mt-1 font-display text-[28px] font-medium">{{ number_format($stats['properties']) }}</dd></div>
                <div><dt class="text-[13px] text-white/75">Restaurants</dt><dd class="mt-1 font-display text-[28px] font-medium">{{ number_format($stats['restaurants']) }}</dd></div>
                <div><dt class="text-[13px] text-white/75">Destinations</dt><dd class="mt-1 font-display text-[28px] font-medium">{{ number_format($stats['destinations']) }}</dd></div>
            </dl>
        </div>
    </section>

    {{-- Questions (also FAQPage structured data) --}}
    <section class="{{ $pad }} pt-24">
        <div class="grid gap-10 lg:grid-cols-[1fr_2fr]">
            <div>
                <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Good to know</p>
                <h2 class="mt-2 font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Booking and hosting, answered</h2>
            </div>
            <div class="divide-y divide-line border-y border-line">
                @foreach ($faqs as $question => $answer)
                    <details class="group py-5" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 text-[17px] font-medium text-fg [&::-webkit-details-marker]:hidden">
                            <h3>{{ $question }}</h3>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center border border-line text-brand transition-transform duration-200 group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-3 max-w-3xl text-[15px] leading-relaxed text-fg-2">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing teaser --}}
    @if ($modules->isNotEmpty())
        <section class="{{ $pad }} pt-24">
            <div class="grid gap-10 lg:grid-cols-[1fr_2fr]">
                <div>
                    <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Simple pricing</p>
                    <h2 class="mt-2 font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Switch on only what your place needs</h2>
                    <p class="mt-4 max-w-md text-[16px] leading-relaxed text-fg-2">A guesthouse can start with rooms and bookings. A resort adds the restaurant, housekeeping and staff. Every module has a free trial.</p>
                    <a href="{{ route('marketing.pricing') }}" class="mt-7 inline-flex h-12 items-center bg-brand px-6 text-[15px] font-semibold text-white transition-colors hover:bg-brand-deep">See full pricing</a>
                </div>
                <ul class="grid gap-px border border-line bg-line sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($modules->take(9) as $module)
                        <li class="flex items-start gap-4 bg-white p-5">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center bg-brand-soft text-brand">@include('marketing.partials.module-icon', ['slug' => $module->slug])</span>
                            <span class="min-w-0">
                                <span class="block text-[15px] font-semibold text-fg">{{ $module->name }}</span>
                                <span class="block text-[14px] text-fg-3">
                                    @if ($module->monthly_price_cents === 0) Included @else ₱{{ number_format($module->monthly_price_cents / 100, 0) }} / month @endif
                                    @if ($module->trial_days > 0) · {{ $module->trial_days }}-day trial @endif
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-public-layout>
