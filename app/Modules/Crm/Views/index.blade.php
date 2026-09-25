<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Guests</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Everyone who stayed, ate or booked a table with you.</p>
        </div>
        <form method="GET" class="flex gap-2">
            @if ($segment)<input type="hidden" name="segment" value="{{ $segment }}" />@endif
            <input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Name, email or phone" aria-label="Search guests" />
            <button type="submit" class="btn btn-sm btn-ghost">Search</button>
        </form>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        <a href="{{ route('crm.index') }}" class="btn btn-sm {{ $segment ? 'btn-ghost' : 'btn-dark' }}">All</a>
        @foreach ($segments as $key => $s)
            <a href="{{ route('crm.index', ['segment' => $key]) }}" class="btn btn-sm {{ $segment === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $s['label'] }} <span class="badge badge-gray">{{ $s['count'] }}</span></a>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('email')" />

    <div class="grid grid-cols-3 gap-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Guest</th><th class="text-right">Stays</th><th class="text-right">Orders</th><th class="text-right">Spend</th><th>Last seen</th></tr></thead>
                <tbody>
                    @forelse ($contacts as $contact)
                        <tr>
                            <td>
                                <a href="{{ route('crm.show', $contact->id) }}"><strong>{{ $contact->name }}</strong></a>
                                @if ($contact->is_vip)<span class="badge badge-amber">VIP</span>@endif
                                <br><small style="color:var(--text-3)">{{ $contact->email ?? $contact->phone ?? '—' }}</small>
                                @foreach ($contact->tags as $tag)<span class="badge badge-gray">{{ $tag->name }}</span>@endforeach
                            </td>
                            <td class="text-right">{{ $contact->bookings_count }}</td>
                            <td class="text-right">{{ $contact->orders_count + $contact->reservations_count }}</td>
                            <td class="text-right">₱{{ number_format((float) $contact->total_spend, 0) }}</td>
                            <td style="color:var(--text-2)">{{ $contact->last_activity_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No guests match.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $contacts->links() }}</div>
        </div>

        <div class="space-y-4">
            @if (auth()->user()->hasPermissionTo('crm.manage'))
                <form method="POST" action="{{ route('crm.store') }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Add a guest</h2>
                    <input name="name" type="text" required class="form-input" placeholder="Full name" aria-label="Name" />
                    <input name="email" type="email" class="form-input" placeholder="Email" aria-label="Email" />
                    <input name="phone" type="text" class="form-input" placeholder="Phone" aria-label="Phone" />
                    <button type="submit" class="btn btn-primary w-full">Add</button>
                </form>
            @endif
            <div class="card p-6 text-sm">
                <h2 class="text-lg">Tags</h2>
                <div class="flex flex-wrap gap-1 mt-2">
                    @forelse ($tags as $tag)
                        <a href="{{ route('crm.index', ['tag' => $tag->id]) }}" class="badge badge-gray">{{ $tag->name }} · {{ $tag->contacts_count }}</a>
                    @empty
                        <span style="color:var(--text-3)">No tags yet.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
