@php
    use App\Modules\Maintenance\Models\MaintenanceTicket as MT;
    $priorityBadge = fn ($p) => match ($p) { 'urgent' => 'badge-red', 'high' => 'badge-amber', default => 'badge-gray' };
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Maintenance</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Repair tickets across your properties.</p>
        </div>
        <form method="GET" class="flex gap-2 items-center">
            <select name="status" class="form-input" onchange="this.form.submit()" aria-label="Status">
                <option value="active" @selected(($filters['status'] ?? 'active') === 'active')>Active</option>
                @foreach ([MT::OPEN, MT::IN_PROGRESS, MT::ON_HOLD, MT::RESOLVED, MT::CLOSED] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                @endforeach
            </select>
            <select name="priority" class="form-input" onchange="this.form.submit()" aria-label="Priority">
                <option value="">Any priority</option>
                @foreach (MT::PRIORITIES as $priority)
                    <option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
            <label class="text-sm flex items-center gap-1"><input type="checkbox" name="mine" value="1" @checked(request()->boolean('mine')) onchange="this.form.submit()" /> Mine</label>
        </form>
    </div>

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Ticket</th><th>Where</th><th>Priority</th><th>Status</th><th>Assigned</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><a href="{{ route('maintenance.show', $ticket->reference) }}"><strong>{{ $ticket->reference }}</strong></a><br><small style="color:var(--text-2)">{{ $ticket->title }}</small></td>
                            <td style="color:var(--text-2)">{{ $ticket->property?->name }}{{ $ticket->room ? ' · '.$ticket->room->room_number : '' }}@if ($ticket->room_out_of_order) <span class="badge badge-red">OOO</span>@endif</td>
                            <td><span class="badge {{ $priorityBadge($ticket->priority) }}">{{ ucfirst($ticket->priority) }}</span></td>
                            <td><span class="badge badge-gray">{{ $ticket->statusLabel() }}</span></td>
                            <td style="color:var(--text-2)">{{ $ticket->assignee?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No tickets.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $tickets->links() }}</div>
        </div>

        @if (auth()->user()->hasPermissionTo('maintenance.work'))
            <form method="POST" action="{{ route('maintenance.store') }}" class="card p-6 space-y-2" x-data="{ property: '{{ $properties->first()?->id }}' }">
                @csrf
                <h2 class="text-lg">New ticket</h2>
                <select name="property_id" class="form-input" x-model="property" aria-label="Property">
                    @foreach ($properties as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <select name="room_id" class="form-input" aria-label="Room">
                    <option value="">Common area / no room</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" x-show="property == '{{ $room->property_id }}'">Room {{ $room->room_number }}</option>
                    @endforeach
                </select>
                <input name="title" type="text" required class="form-input" placeholder="Pool pump noisy" aria-label="Title" />
                <textarea name="description" rows="3" class="form-input" placeholder="Details" aria-label="Description"></textarea>
                <div class="flex gap-2">
                    <select name="category" class="form-input" aria-label="Category">
                        @foreach (MT::CATEGORIES as $category)
                            <option value="{{ $category }}">{{ in_array($category, ['hvac', 'it'], true) ? strtoupper($category) : ucfirst($category) }}</option>
                        @endforeach
                    </select>
                    <select name="priority" class="form-input" aria-label="Priority">
                        @foreach (MT::PRIORITIES as $priority)
                            <option value="{{ $priority }}" @selected($priority === 'normal')>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach (['title', 'property_id', 'room_id', 'category', 'priority'] as $field)
                    <x-input-error :messages="$errors->get($field)" />
                @endforeach
                <button type="submit" class="btn btn-primary w-full">Open ticket</button>
            </form>
        @endif
    </div>
</x-app-layout>
