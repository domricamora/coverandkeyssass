@php
    use App\Modules\RestaurantManagement\Models\TableReservation as TR;
    $canManage = auth()->user()->hasPermissionTo('reservations.manage');
    $badge = fn ($s) => match ($s) {
        TR::CONFIRMED, TR::SEATED => 'badge-green',
        TR::PENDING => 'badge-amber',
        TR::COMPLETED => 'badge-blue',
        default => 'badge-gray',
    };
    $actions = [
        TR::CONFIRMED => ['Confirm', 'btn-primary'],
        TR::SEATED => ['Seat', 'btn-dark'],
        TR::COMPLETED => ['Complete', 'btn-ghost'],
        TR::NO_SHOW => ['No-show', 'btn-ghost'],
        TR::CANCELLED => ['Cancel', 'btn-danger'],
    ];
    $byTable = $reservations->groupBy('restaurant_table_id');
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Reservations — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $date->format('l, M j, Y') }} · {{ $reservations->whereIn('status', TR::BLOCKING)->sum('party_size') }} covers booked</p>
        </div>
        <div class="flex gap-2 items-center">
            <a class="btn btn-sm btn-ghost" href="{{ route('restaurants.reservations', [$restaurant, 'date' => $date->subDay()->toDateString()]) }}" aria-label="Previous day">←</a>
            <form method="GET" action="{{ route('restaurants.reservations', $restaurant) }}">
                <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-input" onchange="this.form.submit()" aria-label="Date" />
            </form>
            <a class="btn btn-sm btn-ghost" href="{{ route('restaurants.reservations', [$restaurant, 'date' => $date->addDay()->toDateString()]) }}" aria-label="Next day">→</a>
            <a href="{{ route('restaurants.show', $restaurant) }}" class="btn btn-ghost">Back</a>
        </div>
    </div>

    <x-input-error :messages="$errors->get('status')" />
    <x-input-error :messages="$errors->get('time')" />
    <x-input-error :messages="$errors->get('party_size')" />

    {{-- Day calendar: one row per table, reservations in time order. --}}
    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th style="width:140px">Table</th><th>Reservations</th></tr></thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr>
                        <td><strong style="color:var(--text)">{{ $table->label }}</strong><br><small style="color:var(--text-3)">{{ $table->seats }} seats</small></td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                @forelse ($byTable->get($table->id, collect()) as $r)
                                    <span class="badge {{ $badge($r->status) }}" title="{{ $r->guest_name }} · {{ $r->statusLabel() }}">
                                        {{ $r->reserved_at->format('g:i') }}–{{ $r->ends_at->format('g:i A') }} · {{ $r->party_size }}p · {{ $r->guest_name }}
                                    </span>
                                @empty
                                    <small style="color:var(--text-3)">Free all day</small>
                                @endforelse
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="py-6 text-center text-sm" style="color:var(--text-3)">No active tables — add some on the Tables page first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-3 gap-4 mt-6">
        <div class="card p-6" style="grid-column:span 2">
            <h2 class="text-lg">Bookings</h2>
            @forelse ($reservations as $r)
                <div class="mt-4 pt-4 flex justify-between gap-4" style="border-top:1px solid var(--border)">
                    <div class="text-sm" style="color:var(--text-2)">
                        <strong style="color:var(--text)">{{ $r->reserved_at->format('g:i A') }}</strong>
                        · {{ $r->guest_name }} · party of {{ $r->party_size }} · table {{ $r->table?->label ?? '—' }}
                        <span class="badge {{ $badge($r->status) }}">{{ $r->statusLabel() }}</span>
                        <br><small style="color:var(--text-3)">{{ $r->reference }} · {{ ucfirst($r->source) }}{{ $r->guest_phone ? ' · '.$r->guest_phone : '' }}</small>
                        @if ($r->special_requests)<br><em>“{{ $r->special_requests }}”</em>@endif
                    </div>
                    @if ($canManage)
                        <div class="flex gap-1 flex-wrap justify-end">
                            @foreach (TR::TRANSITIONS[$r->status] ?? [] as $to)
                                <form method="POST" action="{{ route('restaurants.reservations.transition', [$restaurant, $r->reference]) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $to }}" />
                                    <button type="submit" class="btn btn-sm {{ $actions[$to][1] }}">{{ $actions[$to][0] }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="mt-2 text-sm" style="color:var(--text-3)">No reservations on this day.</p>
            @endforelse
        </div>

        @if ($canManage)
            <form method="POST" action="{{ route('restaurants.reservations.store', $restaurant) }}" class="card p-6 space-y-3">
                @csrf
                <h2 class="text-lg">New reservation</h2>
                <input type="date" name="date" value="{{ old('date', $date->toDateString()) }}" required class="form-input" aria-label="Date" />
                <input type="time" name="time" value="{{ old('time', $slots[0] ?? '19:00') }}" step="900" required class="form-input" aria-label="Time" />
                <input type="number" name="party_size" min="1" value="{{ old('party_size', 2) }}" required class="form-input" aria-label="Party size" />
                <input type="text" name="guest_name" value="{{ old('guest_name') }}" required class="form-input" placeholder="Guest name" aria-label="Guest name" />
                <input type="text" name="guest_phone" value="{{ old('guest_phone') }}" class="form-input" placeholder="Phone" aria-label="Phone" />
                <select name="restaurant_table_id" class="form-input" aria-label="Table">
                    <option value="">Best available table</option>
                    @foreach ($tables as $table)
                        <option value="{{ $table->id }}">{{ $table->label }} ({{ $table->seats }})</option>
                    @endforeach
                </select>
                <textarea name="special_requests" rows="2" class="form-input" placeholder="Special requests" aria-label="Special requests">{{ old('special_requests') }}</textarea>
                <button type="submit" class="btn btn-primary w-full">Book table</button>
                @if ($slots !== [])
                    <p class="text-sm" style="color:var(--text-3)">Online slots today: {{ $slots[0] }}–{{ end($slots) }}</p>
                @endif
            </form>
        @endif
    </div>
</x-app-layout>
