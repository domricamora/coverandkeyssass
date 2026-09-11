<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Your businesses</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Select a business to work in, or create a new one.</p>
        </div>
        <a href="{{ route('tenants.create') }}" class="btn btn-primary">+ New business</a>
    </div>

    @if ($tenants->isEmpty())
        <div class="card mt-8 p-10 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full" style="background:var(--gold-dim);color:var(--brown)">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
            </div>
            <h2 class="mt-4 font-display text-xl font-semibold" style="color:var(--text)">No businesses yet</h2>
            <p class="mx-auto mt-2 max-w-sm text-sm" style="color:var(--text-3)">
                Create your first business account — a hotel, resort, guesthouse or restaurant — to get started.
            </p>
            <a href="{{ route('tenants.create') }}" class="btn btn-primary mt-6">Create your first business</a>
        </div>
    @else
        <div class="table-wrap card mt-8">
            <table class="table">
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $tenant)
                        <tr>
                            <td>
                                <strong style="color:var(--text)">{{ $tenant->name }}</strong>
                                <br><small style="color:var(--text-3)">{{ $tenant->slug }}</small>
                            </td>
                            <td style="color:var(--text-2)">{{ ucfirst($tenant->business_type) }}</td>
                            <td>
                                @if ($currentTenantId === $tenant->id)
                                    <span class="badge badge-green">Active</span>
                                @else
                                    <span class="badge badge-gray">{{ ucfirst($tenant->status) }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('tenants.switch', $tenant) }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $currentTenantId === $tenant->id ? 'btn-dark' : 'btn-primary' }}">
                                        {{ $currentTenantId === $tenant->id ? 'Open' : 'Select' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>