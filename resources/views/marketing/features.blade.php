<x-public-layout :title="$title" description="Every Cover & Keys module explained: what each one does, what it costs and what ships next." :show-search="false" :breadcrumbs="['Features' => null]">
    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Platform</span>
            <h1>Features</h1>
            <p>
                Every module can be switched on per business, with its own limits, trial period and
                dependencies. Turn on what your property runs today and add the rest as you grow.
            </p>
        </div>

        @forelse ($grouped as $category => $items)
            <div class="listing-block">
                <h2>{{ Str::headline($category) }}</h2>
                <div class="feature-grid">
                    @foreach ($items as $module)
                        <article class="card feature feature--module">
                            <header class="feature__head">
                                <span class="feature__ico">@include('marketing.partials.module-icon', ['slug' => $module->slug, 'size' => 22])</span>
                                <div>
                                    <h3>{{ $module->name }}</h3>
                                    <p class="feature__price">
                                        @if ($module->monthly_price_cents === 0)
                                            Included in every account
                                        @else
                                            ₱{{ number_format($module->monthly_price_cents / 100, 0) }} / month
                                        @endif
                                    </p>
                                </div>
                            </header>
                            <p>{{ $module->description }}</p>

                            @if ($module->features->isNotEmpty())
                                <ul class="feature__list">
                                    @foreach ($module->features as $feature)
                                        <li>
                                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            <span>
                                                <strong>{{ $feature->name }}</strong>
                                                @if ($feature->description)
                                                    <span class="muted">{{ $feature->description }}</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="empty">The module catalogue has not been seeded yet.</div>
        @endforelse
    </section>

    <section class="section section--raised">
        <div class="container">
            <div class="section-head">
                <h2>Delivery order</h2>
                <p>Phases from the master plan. A phase is marked shipped only after its tests pass.</p>
            </div>

            @include('marketing.partials.roadmap')
        </div>
    </section>

    <section class="host-cta">
        <div class="container section host-cta__inner">
            <div>
                <h2>Pick the modules your property actually runs</h2>
                <p>Start with the free foundation and the marketplace, then switch modules on as you grow.</p>
            </div>
            <div class="hero__actions">
                <a class="btn btn-primary" href="{{ route('register') }}">Get started</a>
                <a class="btn btn-light" href="{{ route('marketing.pricing') }}">See pricing</a>
            </div>
        </div>
    </section>
</x-public-layout>
