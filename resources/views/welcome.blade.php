<x-guest-layout>
    <section class="hero">
        <div class="container hero__inner">
            <span class="pill-badge pill-badge--amber">Operating System for Hospitality</span>
            <h1 class="mt-4">One system. Every property. Total control.</h1>
            <p>
                Cover & Keys is the operating platform for hotels, resorts and guesthouses —
                properties, reservations, housekeeping, and your team, unified in one place.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('register') }}" class="btn btn-primary">Get started</a>
                <a href="{{ route('login') }}" class="btn btn-ghost">I already have an account</a>
            </div>
            <div class="hero__trust">
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0v3m0-3h3m-3 0H9m3-12a9 9 0 100 18 9 9 0 000-18z"/></svg>
                    RBAC &amp; audit trail
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                    Multi-property ready
                </span>
                <span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3l3.06 3.06a9 9 0 1012.73 12.73L21 21M12 7v5l3 3"/></svg>
                    Foundation first
                </span>
            </div>
        </div>
    </section>

    <section class="container py-16">
        <h2 class="dash-h2">Built for</h2>
        <div class="card mt-6 p-6">
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach (['Hotels', 'Resorts', 'Bed & Breakfasts', 'Guesthouses', 'Vacation rentals', 'Hostels'] as $type)
                    <li class="flex items-center gap-3">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $type }}
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</x-guest-layout>