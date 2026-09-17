<x-public-layout :title="$title" description="Talk to the Cover & Keys team about onboarding properties, module configuration and data migration." :show-search="false">
    <section class="container section">
        <div class="section-head">
            <span class="eyebrow">Contact</span>
            <h1>Let's get your property set up</h1>
            <p>
                Cover &amp; Keys is built for hotels, resorts, guesthouses and restaurants in the
                Philippines. Reach out for onboarding, module configuration or a walkthrough of
                the platform.
            </p>
        </div>

        <div class="grid grid-3">
            <div class="card">
                <h3>Sales &amp; onboarding</h3>
                @if ($contact['sales_email'])
                    <p><a class="chip-inline" href="mailto:{{ $contact['sales_email'] }}">{{ $contact['sales_email'] }}</a></p>
                @else
                    <p class="muted">
                        Not published yet. Set <code>CONTACT_SALES_EMAIL</code> in <code>.env</code>
                        to show a sales address here.
                    </p>
                @endif
                <p class="muted">Onboarding, module selection and demo bookings.</p>
            </div>

            <div class="card">
                <h3>Support</h3>
                @if ($contact['support_email'])
                    <p><a class="chip-inline" href="mailto:{{ $contact['support_email'] }}">{{ $contact['support_email'] }}</a></p>
                @else
                    <p class="muted">
                        Not published yet. Set <code>CONTACT_SUPPORT_EMAIL</code> in <code>.env</code>
                        to show a support address here.
                    </p>
                @endif
                <p class="muted">Account access, roles, billing questions and incidents.</p>
            </div>

            <div class="card">
                <h3>Phone &amp; office</h3>
                @if ($contact['phone'])
                    <p class="chip-inline">{{ $contact['phone'] }}</p>
                @endif
                @if ($contact['address'])
                    <p class="muted">{{ $contact['address'] }}</p>
                @endif
                @if (! $contact['phone'] && ! $contact['address'])
                    <p class="muted">
                        Not published yet. Set <code>CONTACT_PHONE</code> and
                        <code>CONTACT_ADDRESS</code> in <code>.env</code>.
                    </p>
                @endif
            </div>
        </div>

        <div class="listing-block">
            <h2>What helps us quote quickly</h2>
            <ul class="amenities">
                <li>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Property type and how many rooms or tables you operate
                </li>
                <li>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Which operations you want covered first (front desk, restaurant, housekeeping)
                </li>
                <li>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    How many staff need accounts and which roles they hold
                </li>
                <li>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Existing data you would like migrated (rooms, guests, bookings)
                </li>
            </ul>
        </div>

        <div class="host-cta" style="margin-top:34px;border-radius:var(--radius-lg);">
            <div class="host-cta__inner">
                <div>
                    <h2>Prefer to explore first?</h2>
                    <p>Create an account and switch modules on from the dashboard — no payment method required for trials.</p>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <a class="btn btn-primary" href="{{ route('register') }}">Create your account</a>
                    <a class="btn btn-light" href="{{ route('marketplace.hotels') }}">Browse the marketplace</a>
                </div>
            </div>
        </div>
    </section>
</x-public-layout>