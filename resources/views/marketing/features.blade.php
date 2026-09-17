<x-public-layout :title="$title" description="Every Cover & Keys module explained — what ships today and what is next on the roadmap." :show-search="false">
    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Platform</span>
            <h1>Features</h1>
            <p>
                Cover &amp; Keys is a modular hospitality platform. Each module below is
                independently enable-able per business, with its own limits, trial period and
                dependencies — all managed from the Super Admin area.
            </p>
        </div>

        @forelse ($grouped as $category => $items)
            <div class="listing-block">
                <h2>{{ Str::headline($category) }}</h2>
                <div class="grid grid-3">
                    @foreach ($items as $module)
                        <div class="card feature">
                            <h3>{{ $module->name }}</h3>
                            <p>{{ $module->description }}</p>

                            @if ($module->features->isNotEmpty())
                                <ul class="amenities" style="grid-template-columns:1fr;gap:8px;margin-top:10px;">
                                    @foreach ($module->features as $feature)
                                        <li>
                                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            <span>
                                                <strong>{{ $feature->name }}</strong>
                                                @if ($feature->description)
                                                    <span class="muted" style="display:block;">{{ $feature->description }}</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <p class="meta-row mt-2">
                                @if ($module->monthly_price_cents === 0)
                                    <span class="badge badge-amber">Included in every account</span>
                                @else
                                    <span class="chip-inline">₱{{ number_format($module->monthly_price_cents / 100, 0) }} / month</span>
                                @endif
                                @if (! $module->is_core)
                                    <span class="chip-inline">Module</span>
                                @else
                                    <span class="chip-inline">Core</span>
                                @endif
                            </p>
                        </div>
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
                <span class="eyebrow">Roadmap</span>
                <h2>Delivery order</h2>
                <p>Phases from the master plan, with the ones already shipped verified by tests.</p>
            </div>

            <div class="host-card-grid">
                @foreach ($roadmap as $item)
                    <div class="host-os-card {{ $item['status'] === 'planned' ? 'locked' : '' }}">
                        <span class="os-icon" aria-hidden="true"><strong>{{ str_pad((string) $item['phase'], 2, '0', STR_PAD_LEFT) }}</strong></span>
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
        </div>
    </section>

    <section class="host-cta">
        <div class="container section host-cta__inner">
            <div>
                <h2>Pick the modules your property actually runs</h2>
                <p>Start with the foundation and the marketplace, then switch modules on as you grow.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="btn btn-primary" href="{{ route('register') }}">Create your account</a>
                <a class="btn btn-light" href="{{ route('marketing.pricing') }}">See pricing</a>
            </div>
        </div>
    </section>
</x-public-layout>