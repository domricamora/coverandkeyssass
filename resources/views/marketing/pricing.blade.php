<x-public-layout :title="$title" description="Module pricing for Cover & Keys. Pay only for the modules your property runs, with free trials on operational modules." :show-search="false"
    :breadcrumbs="['Pricing' => null]" :schema="[...\App\Support\Seo::productOffers($modules->where('monthly_price_cents', '>', 0)), \App\Support\Seo::faq($faqs)]">
    @php
        $check = '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>';
    @endphp

    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Pricing</span>
            <h1>Pay for the modules you run</h1>
            <p>
                The foundation is free for every business. Everything else is a module you switch
                on when you need it, most with a free trial. Prices below are read live from the
                module catalogue.
            </p>
        </div>

        <div class="plan-grid">
            <div class="card plan">
                <h2 class="plan__name">Foundation</h2>
                <p class="plan__price">Free</p>
                <p class="plan__lede">The platform core, included with every business account.</p>
                <ul class="plan__list">
                    <li>{!! $check !!} Multiple properties and restaurants</li>
                    <li>{!! $check !!} Team, roles and granular permissions</li>
                    <li>{!! $check !!} Audit trail for privileged actions</li>
                    <li>{!! $check !!} Marketplace listing and guest wish lists</li>
                </ul>
                <a class="btn btn-outline btn-block" href="{{ route('register') }}">Get started</a>
            </div>

            <div class="card plan plan--featured">
                <h2 class="plan__name">Typical hotel stack</h2>
                <p class="plan__price">₱{{ number_format($sampleTotal / 100, 0) }}<span>/ month</span></p>
                <p class="plan__lede">What most hotels switch on first.</p>
                <ul class="plan__list">
                    @foreach ($sampleStack as $module)
                        <li>{!! $check !!} <span>{{ $module->name }}</span> <span class="plan__item-price">₱{{ number_format($module->monthly_price_cents / 100, 0) }}</span></li>
                    @endforeach
                </ul>
                <a class="btn btn-primary btn-block" href="{{ route('register') }}">Get started</a>
                <p class="plan__note">Enabling a module also enables the modules it depends on.</p>
            </div>

            <div class="card plan">
                <h2 class="plan__name">Marketplace bookings</h2>
                <p class="plan__price">{{ rtrim(rtrim(number_format($commission, 2), '0'), '.') }}%<span>per paid booking or order</span></p>
                <p class="plan__lede">Commission on guest payments taken through the marketplace. No listing fee.</p>
                <ul class="plan__list">
                    <li>{!! $check !!} Deducted before funds reach your host wallet</li>
                    <li>{!! $check !!} Every deduction itemised in the wallet ledger</li>
                    <li>{!! $check !!} Reversed automatically on refunds</li>
                    <li>{!! $check !!} Request payouts from your dashboard</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section section--raised">
        <div class="container">
            <div class="section-head">
                <h2>Every module and its monthly price</h2>
                <p>Yearly billing is ten times the monthly price. Prices are in Philippine pesos.</p>
            </div>

            @if ($grouped->isEmpty())
                <div class="empty">The module catalogue has not been seeded yet.</div>
            @else
                <div class="card table-wrap">
                    <table class="table price-table">
                        <thead>
                            <tr>
                                <th scope="col">Module</th>
                                <th scope="col">What it does</th>
                                <th scope="col">Trial</th>
                                <th scope="col" class="num">Monthly</th>
                            </tr>
                        </thead>
                        @foreach ($grouped as $category => $items)
                            <tbody>
                                <tr class="price-table__group"><th colspan="4" scope="colgroup">{{ Str::headline($category) }}</th></tr>
                                @foreach ($items as $module)
                                    <tr>
                                        <th scope="row">
                                            <span class="price-table__name">@include('marketing.partials.module-icon', ['slug' => $module->slug, 'size' => 18]) {{ $module->name }}</span>
                                        </th>
                                        <td>{{ $module->description }}</td>
                                        <td>{{ $module->trial_days > 0 ? $module->trial_days.' days' : 'None' }}</td>
                                        <td class="num">
                                            <strong>{{ $module->monthly_price_cents === 0 ? 'Free' : '₱'.number_format($module->monthly_price_cents / 100, 0) }}</strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="container section">
        <h2>Common questions</h2>
        <div class="faq">
            @foreach ($faqs as $question => $answer)
                <details class="faq__item">
                    <summary>{{ $question }}</summary>
                    <p>{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section class="host-cta">
        <div class="container section host-cta__inner">
            <div>
                <h2>Try any operational module free</h2>
                <p>Modules with a trial can be switched on without a payment method.</p>
            </div>
            <div class="hero__actions">
                <a class="btn btn-primary" href="{{ route('register') }}">Get started</a>
                <a class="btn btn-light" href="{{ route('marketing.contact') }}">Talk to us</a>
            </div>
        </div>
    </section>
</x-public-layout>
