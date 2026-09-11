<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Users</h1>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="admin-search">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email…" class="form-input" aria-label="Search users" />
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
                    <th>Email</th>
                    <th>Status</th>
                    <th>Roles</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="font-semibold" style="color:var(--text)">{{ $user->name }}</td>
                        <td style="color:var(--text-3)">{{ $user->email }}</td>
                        <td>
                            <span class="badge {{ $user->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ $user->status }}</span>
                        </td>
                        <td class="text-xs" style="color:var(--text-2)">{{ $user->roles->pluck('display_name')->implode(', ') ?: '—' }}</td>
                        <td class="text-right">
                            @if ($user->status === 'active' && ! $user->isPlatformAdmin())
                                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger">Suspend</button>
                                </form>
                            @elseif ($user->status === 'suspended')
                                <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-dark">Activate</button>
                                </form>
                            @else
                                <span class="text-xs" style="color:var(--text-3)">Protected</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>