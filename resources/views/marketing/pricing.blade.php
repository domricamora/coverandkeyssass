<x-public-layout :title="$title" description="Cover & Keys pricing: a free foundation, then pay monthly only for the modules your property runs, with free trials. No listing fees." :show-search="false"
    :breadcrumbs="['Pricing' => null]" :schema="[\App\Support\Seo::software($modules), ...\App\Support\Seo::productOffers($modules->where('monthly_price_cents', '>', 0)), \App\Support\Seo::faq($faqs)]">
    @php
        $pad = 'px-5 lg:px-10 2xl:px-16';
        $tick = '<svg width="18" height="18" fill="none" stroke="var(--primary)" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
        $peso = fn (int $cents) => '₱'.number_format($cents / 100, 0);
    @endphp

    <section class="{{ $pad }} pt-12 lg:pt-16">
        <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Pricing</p>
        <h1 class="mt-3 max-w-3xl font-display text-[clamp(2.3rem,4.6vw,4rem)] font-medium leading-[1.04] tracking-[-0.02em] text-fg">Start free. Pay only for what your place runs.</h1>
        <p class="mt-5 max-w-2xl text-[17px] leading-relaxed text-fg-2">The foundation is free for every business. Everything else is a module you switch on when you need it, most with a free trial. Prices are in Philippine pesos; yearly billing gets two months free.</p>

        <div class="mt-12 grid gap-5 lg:grid-cols-3">
            <div class="flex flex-col border border-line bg-white p-8">
                <h2 class="text-[15px] font-semibold uppercase tracking-[0.08em] text-fg-3">Foundation</h2>
                <p class="mt-4 font-display text-[44px] font-medium leading-none text-fg">Free</p>
                <p class="mt-3 text-[15px] text-fg-2">The platform core, included with every business account.</p>
                <ul class="mt-6 flex-1 space-y-3 text-[15px] text-fg">
                    @foreach (['Multiple properties and restaurants', 'Team, roles and permissions', 'Audit trail for privileged actions', 'Marketplace listing and guest wish lists'] as $item)
                        <li class="flex gap-3">{!! $tick !!} {{ $item }}</li>
                    @endforeach
                </ul>
                <a class="mt-8 inline-flex h-12 items-center justify-center border border-line-strong text-[15px] font-medium text-fg transition-colors hover:border-brand hover:text-brand" href="{{ route('register') }}">Start free</a>
            </div>

            <div class="relative flex flex-col bg-brand p-8 text-white shadow-[0_30px_60px_-30px_rgba(14,100,97,.6)]">
                <span class="absolute right-6 top-6 bg-coral px-2.5 py-1 text-[12px] font-semibold">Most popular</span>
                <h2 class="text-[15px] font-semibold uppercase tracking-[0.08em] text-white/75">Typical hotel</h2>
                <p class="mt-4 font-display text-[44px] font-medium leading-none">{{ $peso($sampleTotal) }}<span class="ml-1 text-[16px] font-normal text-white/75">/ month</span></p>
                <p class="mt-3 text-[15px] text-white/85">What most hotels and resorts switch on first.</p>
                <ul class="mt-6 flex-1 space-y-3 text-[15px]">
                    @foreach ($sampleStack as $module)
                        <li class="flex items-start justify-between gap-3">
                            <span class="flex gap-3"><svg width="18" height="18" fill="none" stroke="#9fe3db" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>{{ $module->name }}</span>
                            <span class="text-white/75">{{ $peso($module->monthly_price_cents) }}</span>
                        </li>
                    @endforeach
                </ul>
                <a class="mt-8 inline-flex h-12 items-center justify-center bg-coral text-[15px] font-semibold text-white transition-colors hover:bg-coral-deep" href="{{ route('register') }}">Start free trial</a>
                <p class="mt-3 text-center text-[13px] text-white/70">Switching a module on also switches on what it depends on.</p>
            </div>

            <div class="flex flex-col border border-line bg-white p-8">
                <h2 class="text-[15px] font-semibold uppercase tracking-[0.08em] text-fg-3">Marketplace bookings</h2>
                <p class="mt-4 font-display text-[44px] font-medium leading-none text-fg">{{ rtrim(rtrim(number_format($commission, 2), '0'), '.') }}%</p>
                <p class="mt-3 text-[15px] text-fg-2">Per paid online booking or order. No listing fee, no monthly minimum.</p>
                <ul class="mt-6 flex-1 space-y-3 text-[15px] text-fg">
                    @foreach (['Deducted before funds reach your wallet', 'Every deduction itemised', 'Reversed automatically on refunds', 'Request payouts from your dashboard'] as $item)
                        <li class="flex gap-3">{!! $tick !!} {{ $item }}</li>
                    @endforeach
                </ul>
                <a class="mt-8 inline-flex h-12 items-center justify-center border border-line-strong text-[15px] font-medium text-fg transition-colors hover:border-brand hover:text-brand" href="{{ route('marketing.contact') }}">Ask about volume rates</a>
            </div>
        </div>
    </section>

    <section class="{{ $pad }} pt-24">
        <h2 class="font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Every module, every price</h2>
        <p class="mt-3 text-[16px] text-fg-2">Read live from the module catalogue.</p>

        @if ($grouped->isEmpty())
            <p class="mt-8 border border-dashed border-line-strong bg-white p-8 text-center text-fg-3">The module catalogue has not been set up yet.</p>
        @else
            <div class="mt-8 overflow-x-auto border border-line bg-white">
                <table class="w-full min-w-[720px] text-left text-[15px]">
                    <thead class="border-b border-line bg-raised text-[12px] uppercase tracking-[0.08em] text-fg-3">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">Module</th>
                            <th scope="col" class="px-6 py-3 font-semibold">What it does</th>
                            <th scope="col" class="px-6 py-3 font-semibold">Free trial</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">Monthly</th>
                        </tr>
                    </thead>
                    @foreach ($grouped as $category => $items)
                        <tbody class="divide-y divide-line border-b border-line last:border-b-0">
                            <tr><th colspan="4" scope="colgroup" class="bg-soft px-6 py-2.5 text-[12px] font-semibold uppercase tracking-[0.1em] text-brand">{{ \Illuminate\Support\Str::headline($category) }}</th></tr>
                            @foreach ($items as $module)
                                <tr>
                                    <th scope="row" class="px-6 py-4 font-semibold text-fg">
                                        <span class="flex items-center gap-3"><span class="text-brand">@include('marketing.partials.module-icon', ['slug' => $module->slug, 'size' => 18])</span>{{ $module->name }}</span>
                                    </th>
                                    <td class="px-6 py-4 text-fg-2">{{ $module->description }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-fg-2">{{ $module->trial_days > 0 ? $module->trial_days.' days' : '—' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-semibold tabular-nums text-fg">{{ $module->monthly_price_cents === 0 ? 'Free' : $peso($module->monthly_price_cents) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif
    </section>

    <section class="{{ $pad }} pt-24">
        <div class="grid gap-10 lg:grid-cols-[1fr_2fr]">
            <h2 class="font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Questions hosts ask</h2>
            <div class="divide-y divide-line border-y border-line">
                @foreach ($faqs as $question => $answer)
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 text-[17px] font-medium text-fg [&::-webkit-details-marker]:hidden">
                            {{ $question }}
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center border border-line text-brand transition-transform duration-200 group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-3 max-w-3xl text-[15px] leading-relaxed text-fg-2">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    @include('marketing.partials.cta')
</x-public-layout>
