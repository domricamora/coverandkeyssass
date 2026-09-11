<x-app-layout>
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="hoso-badge bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">Super Admin</p>
                <h1 class="mt-2 text-2xl font-semibold text-brand-950 dark:text-sand-50">Platform overview</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.users.index') }}" class="hoso-btn-secondary">Users</a>
                <a href="{{ route('admin.tenants.index') }}" class="hoso-btn-secondary">Tenants</a>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="hoso-card p-5">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Tenants</p>
                <p class="mt-2 font-display text-3xl font-semibold text-brand-900 dark:text-sand-50">{{ $tenantCount }}</p>
                <p class="mt-1 text-xs text-brand-500 dark:text-brand-400">{{ $activeTenants }} active</p>
            </div>
            <div class="hoso-card p-5">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Users</p>
                <p class="mt-2 font-display text-3xl font-semibold text-brand-900 dark:text-sand-50">{{ $userCount }}</p>
            </div>
            <div class="hoso-card p-5">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Modules</p>
                <p class="mt-2 font-display text-3xl font-semibold text-brand-900 dark:text-sand-50">—</p>
                <p class="mt-1 text-xs text-brand-500 dark:text-brand-400">Module engine lands in Phase 02</p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="hoso-card p-6">
                <h2 class="font-display text-lg font-semibold text-brand-900 dark:text-sand-50">Latest tenants</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse ($recentTenants as $tenant)
                        <li class="flex items-center justify-between rounded-lg border border-sand-100 px-3 py-2 dark:border-brand-800">
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="font-semibold text-brand-800 hover:underline dark:text-sand-100">{{ $tenant->name }}</a>
                            <span class="hoso-badge {{ $tenant->status === 'active' ? 'bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100' : 'bg-brass-100 text-brass-800 dark:bg-brass-900 dark:text-brass-200' }}">{{ $tenant->status }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-brand-500 dark:text-brand-400">No tenants yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="hoso-card p-6">
                <h2 class="font-display text-lg font-semibold text-brand-900 dark:text-sand-50">Recent activity</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse ($recentAudit as $entry)
                        <li class="rounded-lg border border-sand-100 px-3 py-2 dark:border-brand-800">
                            <span class="font-mono text-xs font-semibold text-brand-700 dark:text-brand-300">{{ $entry->action }}</span>
                            <span class="ml-2 text-xs text-brand-500 dark:text-brand-400">{{ $entry->user?->name ?? 'system' }} · {{ $entry->created_at->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-brand-500 dark:text-brand-400">No activity recorded yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
