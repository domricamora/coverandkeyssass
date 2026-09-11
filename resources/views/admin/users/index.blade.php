<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="hoso-badge bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">Super Admin</p>
                <h1 class="mt-2 text-2xl font-semibold text-brand-950 dark:text-sand-50">Users</h1>
            </div>
            <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email…" class="hoso-input !mt-0" aria-label="Search users" />
                <button type="submit" class="hoso-btn-secondary">Search</button>
            </form>
        </div>

        <div class="hoso-card mt-6 overflow-hidden">
            <table class="hoso-table">
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
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="font-semibold">{{ $user->name }}</td>
                            <td class="text-brand-500 dark:text-brand-400">{{ $user->email }}</td>
                            <td>
                                <span class="hoso-badge {{ $user->status === 'active' ? 'bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' }}">{{ $user->status }}</span>
                            </td>
                            <td class="text-xs text-brand-600 dark:text-brand-300">{{ $user->roles->pluck('display_name')->implode(', ') ?: '—' }}</td>
                            <td class="text-right">
                                @if ($user->status === 'active' && ! $user->isPlatformAdmin())
                                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400">Suspend</button>
                                    </form>
                                @elseif ($user->status === 'suspended')
                                    <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Activate</button>
                                    </form>
                                @else
                                    <span class="text-xs text-brand-400 dark:text-brand-500">Protected</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm text-brand-500 dark:text-brand-400">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>
