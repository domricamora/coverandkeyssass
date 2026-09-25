<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Platform overview</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-dark btn-sm">Users</a>
            <a href="{{ route('admin.tenants.index') }}" class="btn btn-dark btn-sm">Tenants</a>
            <a href="{{ route('admin.commissions.index') }}" class="btn btn-dark btn-sm">Commissions</a>
            <a href="{{ route('admin.payouts.index') }}" class="btn btn-dark btn-sm">Payouts</a>
            <a href="{{ route('admin.reviews.index') }}" class="btn btn-dark btn-sm">Reviews</a>
        </div>
    </div>

    <div class="stat-grid stat-grid--3">
        <div class="stat card">
            <p class="stat__label">Tenants</p>
            <p class="stat__value">{{ $tenantCount }}</p>
            <p class="stat__sub">{{ $activeTenants }} active</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Users</p>
            <p class="stat__value">{{ $userCount }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Modules</p>
            <p class="stat__value">&mdash;</p>
            <p class="stat__sub">Module engine lands in Phase 02</p>
        </div>
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