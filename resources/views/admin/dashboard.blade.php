<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Platform overview</h1>
        </div>
    </div>

    <nav class="flex flex-wrap gap-2 mt-2" aria-label="Super Admin">
        @foreach ([
            'admin.users.index' => 'Users', 'admin.tenants.index' => 'Businesses', 'admin.modules.index' => 'Modules', 'admin.pricing.index' => 'Pricing',
            'admin.billing.index' => 'Subscriptions', 'admin.bookings.index' => 'Bookings', 'admin.orders.index' => 'Orders', 'admin.payments.index' => 'Payments & refunds',
            'admin.commissions.index' => 'Commissions', 'admin.payouts.index' => 'Payouts', 'admin.reviews.index' => 'Reviews', 'admin.support.index' => 'Support',
            'admin.moderation.index' => 'Reported content', 'admin.taxonomy.index' => 'Categories & locations', 'admin.pages.index' => 'CMS pages', 'admin.settings.index' => 'Settings', 'admin.reports.index' => 'Reports', 'admin.logs.index' => 'Logs',
        ] as $name => $label)
            <a href="{{ route($name) }}" class="btn btn-dark btn-sm">{{ $label }}</a>
        @endforeach
        <a href="{{ route('admin.listings.index', 'properties') }}" class="btn btn-dark btn-sm">Properties</a>
        <a href="{{ route('admin.listings.index', 'restaurants') }}" class="btn btn-dark btn-sm">Restaurants</a>
    </nav>

    <div class="stat-grid stat-grid--3 mt-6">
        @foreach ($stats as [$label, $value, $sub, $link])
            <a href="{{ $link }}" class="stat card" style="color:inherit;text-decoration:none">
                <p class="stat__label">{{ $label }}</p>
                <p class="stat__value">{{ $value }}</p>
                <p class="stat__sub">{{ $sub }}</p>
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2 mt-8">
        <div class="card p-6">
            <h2 class="dash-h2" style="margin-top:0">Latest tenants</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @forelse ($recentTenants as $tenant)
                    <li class="flex items-center justify-between rounded-lg border px-3 py-2" style="border-color:var(--border)">
                        <a href="{{ route('admin.tenants.show', $tenant) }}" class="font-semibold hover:underline" style="color:var(--text)">{{ $tenant->name }}</a>
                        <span class="badge {{ $tenant->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ $tenant->status }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm" style="color:var(--text-3)">No tenants yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="card p-6">
            <h2 class="dash-h2" style="margin-top:0">Recent activity</h2>
            <ul class="mt-4 space-y-2 text-sm">
                @forelse ($recentAudit as $entry)
                    <li class="rounded-lg border px-3 py-2" style="border-color:var(--border)">
                        <span class="font-mono text-xs font-semibold" style="color:var(--gold-deep)">{{ $entry->action }}</span>
                        <span class="ml-2 text-xs" style="color:var(--text-3)">{{ $entry->user?->name ?? 'system' }} &middot; {{ $entry->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm" style="color:var(--text-3)">No activity recorded yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>