<x-public-layout :title="$title" description="Cloud hotel PMS, booking engine and restaurant POS for Philippine hotels, resorts, B&Bs and restaurants: front desk, room chart, housekeeping, payments by GCash, Maya or card, accounting and staff rota in one system."
    :show-search="false" :breadcrumbs="['For hosts' => null]" :schema="[\App\Support\Seo::organization(), \App\Support\Seo::software($modules)]">
    @php
        $pad = 'px-5 lg:px-10 2xl:px-16';
    @endphp

    {{-- Hero: the product itself --}}
    <section class="{{ $pad }} pt-12 lg:pt-16">
        <div class="grid items-end gap-10 lg:grid-cols-[1fr_1fr]">
            <div>
                <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">For hotels, resorts and restaurants</p>
                <h1 class="mt-3 font-display text-[clamp(2.3rem,4.6vw,4rem)] font-medium leading-[1.04] tracking-[-0.02em] text-fg">Everything your place runs on, in one calm screen.</h1>
            </div>
            <div class="lg:pb-2">
                <p class="max-w-xl text-[17px] leading-relaxed text-fg-2"><strong class="font-semibold text-fg">Cover &amp; Keys is a cloud property management system (PMS), booking engine and restaurant POS</strong> for hotels, resorts, B&amp;Bs and restaurants in the Philippines. Guests book on the marketplace, the room lands on your front desk, housekeeping sees the check-out, the restaurant charges to the room and the books balance themselves.</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="inline-flex h-12 items-center bg-brand px-6 text-[15px] font-semibold text-white transition-colors hover:bg-brand-deep">Start free</a>
                    <a href="{{ route('marketing.pricing') }}" class="inline-flex h-12 items-center border border-line-strong bg-white px-6 text-[15px] font-medium text-fg transition-colors hover:border-brand hover:text-brand">See pricing</a>
                </div>
            </div>
        </div>
        <figure class="mt-12 border border-line bg-white p-2 shadow-[0_40px_80px_-40px_rgba(16,37,42,.35)] sm:p-3">
            <img src="{{ asset('img/product/front-desk.webp') }}" alt="The Cover & Keys front desk: today's arrivals, occupancy and a room chart" width="1440" height="900" class="block w-full" loading="eager">
        </figure>
    </section>

    {{-- Jobs to be done --}}
    @php
        $jobs = [
            ['Front desk', 'Today on one screen', 'Arrivals, departures and who is in house, with one-click check-in and check-out. Drag a stay to another room on the chart; the bill follows the guest.', ['Room chart with drag-to-move', 'Folio with payments, charges and refunds', 'Walk-ins, holds and group bookings'], 'img/demo/tour/lobby-3-NNvFZeMbm84.jpg'],
            ['Housekeeping', 'Clean rooms, faster', 'Every check-out drops a cleaning task on the board. Attendants start and finish from their phones, supervisors inspect, and the front desk sees the room turn clean.', ['Task board by room and attendant', 'Inspections and re-cleans', 'Maintenance tickets that block a room'], 'img/demo/tour/room-5-67-sOi7mVIk.jpg'],
            ['Restaurant', 'From table to kitchen to bill', 'Ring up tables on the register, send tickets to the kitchen, split the bill or charge it to the room. Guests book tables and order online from the same menu.', ['Register with daily sessions and Z-report', 'Kitchen tickets and table plan', 'Online orders, delivery and room service'], 'img/demo/tour/dining-2-nulJA9vxJII.jpg'],
            ['Back office', 'Books that balance themselves', 'Every night sold, order rung up and payment taken posts to the ledger. Payouts, commissions and staff costs are there when the accountant asks.', ['Double-entry ledger and reports', 'Staff rota, clock-ins and leave', 'Guest profiles, loyalty and campaigns'], 'img/demo/tour/breakfast-5-xyel_GFkqh4.jpg'],
        ];
    @endphp
    <section class="pt-24">
        @foreach ($jobs as $i => [$kicker, $heading, $body, $points, $photo])
            <div class="grid lg:grid-cols-2 {{ $i % 2 ? '' : 'bg-white' }}">
                <div class="relative min-h-[320px] overflow-hidden {{ $i % 2 ? 'lg:order-2' : '' }}">
                    <img src="{{ asset($photo) }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                </div>
                <div class="px-6 py-16 sm:px-12 lg:px-16 lg:py-24">
                    <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">{{ $kicker }}</p>
                    <h2 class="mt-3 font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">{{ $heading }}</h2>
                    <p class="mt-4 max-w-xl text-[16px] leading-relaxed text-fg-2">{{ $body }}</p>
                    <ul class="mt-7 space-y-3">
                        @foreach ($points as $point)
                            <li class="flex gap-3 text-[15px] text-fg">
                                <svg width="18" height="18" fill="none" stroke="var(--primary)" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Full module catalogue --}}
    <section class="{{ $pad }} pt-24">
        <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Modules</p>
        <h2 class="mt-2 font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Switch on what you need, when you need it</h2>
        <p class="mt-3 max-w-2xl text-[16px] leading-relaxed text-fg-2">Each module has its own price and free trial, and brings anything it depends on with it.</p>

        @forelse ($grouped as $category => $items)
            <h3 class="mt-12 border-b border-line pb-3 text-[13px] font-semibold uppercase tracking-[0.12em] text-fg-3">{{ \Illuminate\Support\Str::headline($category) }}</h3>
            <div class="mt-5 grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($items as $module)
                    <article class="border border-line bg-white p-6">
                        <header class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center bg-brand-soft text-brand">@include('marketing.partials.module-icon', ['slug' => $module->slug, 'size' => 22])</span>
                            <div class="min-w-0">
                                <h4 class="text-[17px] font-semibold text-fg">{{ $module->name }}</h4>
                                <p class="text-[14px] text-fg-3">
                                    @if ($module->monthly_price_cents === 0) Included in every account @else ₱{{ number_format($module->monthly_price_cents / 100, 0) }} / month @endif
                                    @if ($module->trial_days > 0) · {{ $module->trial_days }}-day free trial @endif
                                </p>
                            </div>
                        </header>
                        <p class="mt-4 text-[15px] leading-relaxed text-fg-2">{{ $module->description }}</p>
                        @if ($module->features->isNotEmpty())
                            <ul class="mt-4 space-y-2 border-t border-line pt-4">
                                @foreach ($module->features as $feature)
                                    <li class="flex gap-2.5 text-[14px] text-fg-2">
                                        <svg width="16" height="16" fill="none" stroke="var(--primary)" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                        <span><strong class="font-medium text-fg">{{ $feature->name }}</strong>@if ($feature->description) <span class="text-fg-3">· {{ $feature->description }}</span>@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        @empty
            <p class="mt-8 border border-dashed border-line-strong bg-white p-8 text-center text-fg-3">The module catalogue has not been set up yet.</p>
        @endforelse
    </section>

    @include('marketing.partials.cta')
</x-public-layout>
