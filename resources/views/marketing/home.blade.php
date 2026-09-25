<x-public-layout :title="$title" description="Cover & Keys is the hospitality operating system for hotels, resorts, B&Bs and restaurants: marketplace, front desk, housekeeping and back office in one platform." :schema="[\App\Support\Seo::website()]">
    <section class="hero hero--media">
        <div class="hero__bg" style="background-image: url('{{ asset('media/hero-resort.jpg') }}');">
            {{-- Pexels 4069480 (free licence). Source is attached by the script below only on wide screens without reduced motion. --}}
            <video class="hero__video" muted loop playsinline preload="none" poster="{{ asset('media/hero-resort.jpg') }}" data-src="{{ asset('media/hero-resort.mp4') }}" aria-hidden="true"></video>
        </div>
        <script>
            (function () {
                var v = document.currentScript.previousElementSibling.querySelector('video');
                if (!v || !window.matchMedia('(min-width: 768px) and (prefers-reduced-motion: no-preference)').matches) return;
                v.src = v.dataset.src;
                v.play().catch(function () {});
            })();
        </script>
        <div class="container hero__inner">
            <span class="pill-badge pill-badge--amber">Hospitality operating system</span>
            <h1>One system for every cover and every key.</h1>
            <p>
                The marketplace that fills your rooms, the front desk that runs them and the back
                office that balances the books. Switch on only what your property needs.
            </p>

            <div class="hero__actions">
                <a class="btn btn-primary" href="{{ route('register') }}">Get started</a>
                <a class="btn btn-outline" href="{{ route('marketplace.hotels') }}">Explore stays</a>
            </div>
        </div>
    </section>

    <section class="stat-band" aria-label="Marketplace at a glance">
        <dl class="container stat-band__grid">
            <div>
                <dt>Published stays</dt>
                <dd>{{ number_format($stats['properties']) }}</dd>
            </div>
            <div>
                <dt>Restaurants</dt>
                <dd>{{ number_format($stats['restaurants']) }}</dd>
            </div>
            <div>
                <dt>Destinations</dt>
                <dd>{{ number_format($stats['destinations']) }}</dd>
            </div>
        </dl>
    </section>

    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Marketplace</span>
            <h2>A booking channel that is part of your system</h2>
            <p>
                Listings publish from the property's own dashboard. Rates, photos and availability
                come from the same records the front desk uses, so there is no channel manager to
                reconcile.
            </p>
        </div>

        @if ($featured->isEmpty())
            <div class="empty">
                No listings are published yet. Seed the demo catalogue to preview the marketplace:
                <code>php artisan db:seed --class=MarketplaceDemoSeeder</code>.
            </div>
        @else
            <div class="grid grid-3">
                @foreach ($featured as $property)
                    @include('marketplace::partials.property-card', ['property' => $property, 'favoriteIds' => []])
                @endforeach
            </div>
            <p class="mt-4">
                <a class="btn btn-outline btn-sm" href="{{ route('marketplace.hotels') }}">Browse all stays</a>
                <a class="btn btn-ghost btn-sm" href="{{ route('marketplace.restaurants.index') }}">Restaurants</a>
            </p>
        @endif
    </section>

    <section class="section section--raised">
        <div class="container">
            <div class="section-head">
                <h2>Everything your operation needs, priced separately</h2>
                <p>
                    Each capability is a module with its own price, limits and dependencies. A
                    guesthouse can run Property and Booking. A resort adds Restaurant, Workforce
                    and Analytics.
                </p>
            </div>

            @if ($modules->isEmpty())
                <div class="empty empty--sm">The module catalogue is empty. Seed it with <code>php artisan db:seed --class=ModuleSeeder</code>.</div>
            @else
                <ul class="module-list">
                    @foreach ($modules as $module)
                        <li class="module-list__item">
                            <span class="module-list__icon">@include('marketing.partials.module-icon', ['slug' => $module->slug])</span>
                            <div class="module-list__body">
                                <h3>{{ $module->name }}</h3>
                                <p>{{ $module->description }}</p>
                            </div>
                            <div class="module-list__price">
                                @if ($module->monthly_price_cents === 0)
                                    <strong>Included</strong>
                                @else
                                    <strong>₱{{ number_format($module->monthly_price_cents / 100, 0) }}</strong><span>/ month</span>
                                @endif
                                @if ($module->trial_days > 0)
                                    <small>{{ $module->trial_days }}-day trial</small>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4"><a class="btn btn-outline btn-sm" href="{{ route('marketing.pricing') }}">See full pricing</a></p>
            @endif
        </div>
    </section>

    <section class="container section">
        <div class="section-head">
            <h2>Where the platform is today</h2>
            <p>We ship one phase at a time, and a phase is marked shipped only after its tests pass.</p>
        </div>

        @include('marketing.partials.roadmap')
    </section>

    <section class="host-cta">
        <div class="container section host-cta__inner">
            <div>
                <h2>Bring your property onto Cover &amp; Keys</h2>
                <p>
                    Create an account, set up your business and publish your first listing. Team
                    roles, permissions and the audit trail are ready from day one.
                </p>
            </div>
            <div class="hero__actions">
                <a class="btn btn-primary" href="{{ route('register') }}">Get started</a>
                <a class="btn btn-light" href="{{ route('marketing.features') }}">Tour the platform</a>
            </div>
        </div>
    </section>
</x-public-layout>
