<x-public-layout :title="$title" description="Cover & Keys is the hospitality operating system for hotels, resorts, B&Bs and restaurants — marketplace, front desk, housekeeping and back office in one platform." :schema="[\App\Support\Seo::website()]">
    <section class="hero">
        <div class="hero__bg" style="background-image: radial-gradient(120% 90% at 12% 8%, #1f1f22 0%, #121213 45%, #0a0a0b 100%);"></div>
        <div class="container hero__inner">
            <span class="pill-badge pill-badge--amber">Hospitality operating system</span>
            <h1>One system for every cover and every key.</h1>
            <p>
                Cover &amp; Keys runs the whole property — the public marketplace that fills rooms,
                the front desk that manages them, and the back office that pays for itself.
                Modular: switch on only what your property runs.
            </p>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="btn btn-primary" href="{{ route('register') }}">Start a free trial</a>
                <a class="btn btn-outline" href="{{ route('marketplace.hotels') }}">Explore the marketplace</a>
            </div>

            <div class="hero__trust">
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V7l7-4z"/></svg>
                    Multi-tenant isolation by default
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                    Role-based access with an audit trail
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8"/></svg>
                    Built module by module, in the open
                </span>
            </div>

            <div class="stat-grid stat-grid--3" style="max-width:640px;margin-top:34px;">
                <div class="card stat">
                    <p class="stat__label">Published stays</p>
                    <p class="stat__value">{{ number_format($stats['properties']) }}</p>
                    <p class="stat__sub">live on the marketplace</p>
                </div>
                <div class="card stat">
                    <p class="stat__label">Restaurants</p>
                    <p class="stat__value">{{ number_format($stats['restaurants']) }}</p>
                    <p class="stat__sub">directory listings</p>
                </div>
                <div class="card stat">
                    <p class="stat__label">Destinations</p>
                    <p class="stat__value">{{ number_format($stats['destinations']) }}</p>
                    <p class="stat__sub">with published inventory</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Marketplace</span>
            <h2>A booking channel that is part of your system</h2>
            <p>
                Listings are published from the property's own dashboard, so rates, photos and
                availability come from the same records the front desk works with — no channel
                manager to reconcile.
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
                <span class="eyebrow">Modules</span>
                <h2>Everything your operation needs, priced separately</h2>
                <p>
                    Every capability is a module with its own price, limits and dependencies.
                    A guesthouse can run Property + Booking; a resort can add Restaurant,
                    Housekeeping and Analytics. Rates below are read live from the module engine.
                </p>
            </div>

            @if ($modules->isEmpty())
                <div class="empty empty--sm">The module catalogue is empty — seed it with <code>php artisan db:seed --class=ModuleSeeder</code>.</div>
            @else
                <div class="grid grid-3">
                    @foreach ($modules as $module)
                        <div class="card feature">
                            <span class="feature__ico" aria-hidden="true">
                                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7l8-4 8 4v10l-8 4-8-4V7zM4 7l8 4 8-4M12 21V11"/></svg>
                            </span>
                            <h3>{{ $module->name }}</h3>
                            <p>{{ $module->description }}</p>
                            <p class="meta-row mt-2">
                                @if ($module->monthly_price_cents === 0)
                                    <span class="badge badge-amber">Included</span>
                                @else
                                    <span class="chip-inline">₱{{ number_format($module->monthly_price_cents / 100, 0) }} / month</span>
                                @endif
                                @if ($module->trial_days > 0)
                                    <span class="chip-inline">{{ $module->trial_days }}-day trial</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4"><a class="btn btn-primary btn-sm" href="{{ route('marketing.pricing') }}">See full pricing</a></p>
            @endif
        </div>
    </section>

    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Built in the open</span>
            <h2>Where the platform is today</h2>
            <p>We ship one phase at a time, tested before it is marked done. This is the live roadmap.</p>
        </div>

        <div class="host-card-grid">
            @foreach ($roadmap as $item)
                <div class="host-os-card {{ $item['status'] === 'planned' ? 'locked' : '' }}">
                    <span class="os-icon" aria-hidden="true">
                        <strong>{{ str_pad((string) $item['phase'], 2, '0', STR_PAD_LEFT) }}</strong>
                    </span>
                    <span class="os-label">
                        <strong>{{ $item['title'] }}</strong>
                        <span>{{ $item['summary'] }}</span>
                    </span>
                    <span class="os-badge">
                        @switch($item['status'])
                            @case('complete') Shipped @break
                            @case('current') In progress @break
                            @case('next') Next up @break
                            @default Planned
                        @endswitch
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="host-cta">
        <div class="container section host-cta__inner">
            <div>
                <h2>Bring your property onto Cover &amp; Keys</h2>
                <p>
                    Create an account, set up your business, and publish your first listing to the
                    marketplace. Team roles, permissions and audit logging are already in place.
                </p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="btn btn-primary" href="{{ route('register') }}">Create your account</a>
                <a class="btn btn-light" href="{{ route('marketing.features') }}">Tour the platform</a>
            </div>
        </div>
    </section>
</x-public-layout>