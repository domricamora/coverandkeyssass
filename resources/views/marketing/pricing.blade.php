<x-public-layout :title="$title" description="Module pricing for Cover & Keys. Pay only for the modules your property runs, with free trials on operational modules." :show-search="false"
    :breadcrumbs="['Pricing' => null]" :schema="[...\App\Support\Seo::productOffers($modules->where('monthly_price_cents', '>', 0)), \App\Support\Seo::faq($faqs)]">
    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Pricing</span>
            <h1>Pay for the modules you run</h1>
            <p>
                The foundation (accounts, multi-tenancy, RBAC, audit trail) is included with every
                business. Everything else is a module you can switch on — each with a trial period
                where the module defines one. Prices come straight from the module engine, so this
                page can never fall out of date.
            </p>
        </div>

        <div class="grid grid-3">
            <div class="card feature">
                <span class="feature__ico" aria-hidden="true">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V7l7-4z"/></svg>
                </span>
                <h3>Foundation</h3>
                <p class="stat__value" style="font-size:1.6rem;">Free</p>
                <p>Every business account includes the platform core.</p>
                <ul class="amenities" style="grid-template-columns:1fr;gap:8px;">
                    <li><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Multiple properties &amp; sub-accounts</li>
                    <li><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Team, roles &amp; granular permissions</li>
                    <li><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Audit trail for privileged actions</li>
                    <li><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Marketplace listing &amp; wish lists</li>
                </ul>
                <a class="btn btn-primary btn-block mt-4" href="{{ route('register') }}">Create an account</a>
            </div>

            <div class="card feature">
                <span class="feature__ico" aria-hidden="true">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5"/></svg>
                </span>
                <h3>Typical stay stack</h3>
                <p class="stat__value" style="font-size:1.6rem;">₱{{ number_format($sampleTotal / 100, 0) }}<span style="font-size:.9rem;color:var(--text-3);"> / month</span></p>
                <p>{{ $sampleStack->pluck('name')->implode(' + ') }} — the bundle most hotels start with.</p>
                <ul class="amenities" style="grid-template-columns:1fr;gap:8px;">
                    @foreach ($sampleStack as $module)
                        <li>
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ $module->name }} — ₱{{ number_format($module->monthly_price_cents / 100, 0) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="muted mt-2">Modules are enabled per business; enabling one automatically enables the modules it depends on.</p>
            </div>

            <div class="card feature">
                <span class="feature__ico" aria-hidden="true">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M21 7v6h-6"/></svg>
                </span>
                <h3>Marketplace commission</h3>
                <p class="stat__value" style="font-size:1.6rem;">Phase 08</p>
                <p>Host wallet, commissions and payouts land with the finance phase — platform-wide rates with per-property overrides.</p>
                <p class="muted">Until then, published listings carry no platform booking fee.</p>
            </div>
        </div>
    </section>

    <section class="section section--raised">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Full catalogue</span>
                <h2>Every module and its monthly price</h2>
            </div>

            @forelse ($grouped as $category => $items)
                <div class="listing-block">
                    <h2>{{ Str::headline($category) }}</h2>
                    <div class="card table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Module</th>
                                    <th scope="col">What it does</th>
                                    <th scope="col">Trial</th>
                                    <th scope="col">Monthly</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $module)
                                    <tr>
                                        <td>
                                            <strong>{{ $module->name }}</strong>
                                            @if ($module->is_core)
                                                <span class="badge badge-amber">Core</span>
                                            @endif
                                        </td>
                                        <td>{{ $module->description }}</td>
                                        <td>{{ $module->trial_days > 0 ? $module->trial_days.' days' : '—' }}</td>
                                        <td>
                                            @if ($module->monthly_price_cents === 0)
                                                <strong>Free</strong>
                                            @else
                                                <strong>₱{{ number_format($module->monthly_price_cents / 100, 0) }}</strong>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="empty">The module catalogue has not been seeded yet.</div>
            @endforelse

            <p class="muted mt-4">
                Prices are in Philippine pesos, billed monthly, and configured by the platform team.
                Module limits and promotional rates are set per business in the Super Admin area.
            </p>
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
                <p>Modules with a trial period can be switched on for a business without a payment method.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a class="btn btn-primary" href="{{ route('register') }}">Create your account</a>
                <a class="btn btn-light" href="{{ route('marketing.contact') }}">Talk to us</a>
            </div>
        </div>
    </section>
</x-public-layout>