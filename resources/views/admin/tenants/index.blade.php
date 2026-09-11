<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="hoso-badge bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">Super Admin</p>
                <h1 class="mt-2 text-2xl font-semibold text-brand-950 dark:text-sand-50">Tenants</h1>
            </div>
            <div class="flex gap-2">
                <form method="GET" action="{{ route('admin.tenants.index') }}" class="flex gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search tenants…" class="hoso-input !mt-0" aria-label="Search tenants" />
                    <button type="submit" class="hoso-btn-secondary">Search</button>
                </form>
                <a href="{{ route('admin.tenants.create') }}" class="hoso-btn-primary">+ New tenant</a>
            </div>
        </div>

        <div class="hoso-card mt-6 overflow-hidden">
            <table class="hoso-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Members</th>
                        <th class="text-right">Open</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr wire:key="tenant-{{ $tenant->id }}">
                            <td class="font-semibold">{{ $tenant->name }}</td>
                            <td class="text-brand-500 dark:text-brand-400">{{ ucfirst($tenant->business_type) }}</td>
                            <td>
                                <span class="hoso-badge {{ $tenant->status === 'active' ? 'bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100' : 'bg-brass-100 text-brass-800 dark:bg-brass-900 dark:text-brass-200' }}">{{ $tenant->status }}</span>
                            </td>
                            <td>{{ $tenant->users_count }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm text-brand-500 dark:text-brand-400">No tenants found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $tenants->links() }}</div>
    </div>
</x-app-layout>
