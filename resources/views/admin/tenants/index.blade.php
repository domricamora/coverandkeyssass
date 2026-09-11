<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Tenants</h1>
        </div>
        <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary">+ New tenant</a>
    </div>

    <form method="GET" action="{{ route('admin.tenants.index') }}" class="admin-search">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search tenants…" class="form-input" aria-label="Search tenants" />
        <button type="submit" class="btn btn-dark btn-sm">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/></svg>
            Search
        </button>
    </form>

    <div class="table-wrap card">
        <table class="table">
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
                    <tr>
                        <td class="font-semibold" style="color:var(--text)">{{ $tenant->name }}</td>
                        <td style="color:var(--text-2)">{{ ucfirst($tenant->business_type) }}</td>
                        <td>
                            <span class="badge {{ $tenant->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ $tenant->status }}</span>
                        </td>
                        <td style="color:var(--text-2)">{{ $tenant->users_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-sm btn-dark">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No tenants found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tenants->links() }}</div>
</x-app-layout>