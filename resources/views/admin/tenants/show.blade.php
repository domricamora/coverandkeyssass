<x-app-layout>
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="hoso-badge bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">Super Admin</p>
                <h1 class="mt-2 text-2xl font-semibold text-brand-950 dark:text-sand-50">{{ $tenant->name }}</h1>
                <p class="mt-1 text-sm text-brand-500 dark:text-brand-400">{{ ucfirst($tenant->business_type) }} · {{ $tenant->slug }} · {{ $tenant->users_count }} member(s)</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($tenant->status === 'active')
                    <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}">
                        @csrf
                        <button type="submit" class="hoso-btn-secondary">Suspend</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.tenants.activate', $tenant) }}">
                        @csrf
                        <button type="submit" class="hoso-btn-secondary">Activate</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}"
                      onsubmit="return confirm('Delete this tenant? Its members lose access immediately.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="hoso-btn-danger">Delete</button>
                </form>
            </div>
        </div>

        <div class="hoso-card mt-6 overflow-hidden">
            <table class="hoso-table">
                <thead>
                    <tr><th>Member</th><th>Email</th><th>Account status</th></tr>
                </thead>
                <tbody>
                    @forelse ($members as $membership)
                        <tr>
                            <td class="font-semibold">{{ $membership->user->name }}</td>
                            <td class="text-brand-500 dark:text-brand-400">{{ $membership->user->email }}</td>
                            <td>{{ ucfirst($membership->user->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-sm text-brand-500 dark:text-brand-400">No members.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <a href="{{ route('admin.tenants.index') }}" class="mt-6 inline-block text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">← Back to tenants</a>
    </div>
</x-app-layout>
