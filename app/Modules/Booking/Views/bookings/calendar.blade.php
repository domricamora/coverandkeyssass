<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Room calendar</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Occupied nights and blocks, 14 days from {{ $start->format('M j, Y') }}.</p>
        </div>
        <a href="{{ route('bookings.index') }}" class="btn btn-ghost">Back to bookings</a>
    </div>

    <form method="GET" class="card p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label for="cal_property" class="form-label">Property</label>
            <select id="cal_property" name="property" class="form-input">
                @foreach ($properties as $option)
                    <option value="{{ $option->slug }}" @selected($property?->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cal_start" class="form-label">From</label>
            <input id="cal_start" type="date" name="start" value="{{ $start->toDateString() }}" class="form-input" />
        </div>
        <button class="btn btn-dark" type="submit">Show</button>
    </form>

    <div class="table-wrap card">
        <table class="table" style="font-size:12px">
            <thead>
                <tr>
                    <th>Room</th>
                    @foreach ($days as $day)
                        <th class="text-center">{{ $day->format('D') }}<br>{{ $day->format('j') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rooms as $room)
                    <tr>
                        <td><strong>{{ $room->room_number }}</strong><br><small style="color:var(--text-3)">{{ $room->roomType?->name }}</small></td>
                        @foreach ($days as $day)
                            @php
                                $date = $day->toDateString();
                                $cell = $occupied[$room->id.'|'.$date] ?? null;
                                $blocked = $blocks->first(fn ($b) => ($b->room_id === null ? $b->room_type_id === $room->room_type_id : $b->room_id === $room->id)
                                    && $b->start_date->toDateString() <= $date && $b->end_date->toDateString() >= $date);
                            @endphp
                            <td class="text-center" style="{{ $cell ? 'background:var(--surface-2)' : ($blocked ? 'background:repeating-linear-gradient(45deg,transparent,transparent 4px,var(--surface-2) 4px,var(--surface-2) 8px)' : '') }}">
                                @if ($cell)
                                    <a href="{{ route('bookings.show', $cell->reference) }}" title="{{ $cell->guest_name }} · {{ Str::headline($cell->status) }}">{{ Str::limit($cell->guest_name, 8, '…') }}</a>
                                @elseif ($blocked)
                                    <span title="{{ Str::headline($blocked->reason) }}">Blocked</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="15" class="py-8 text-center text-sm" style="color:var(--text-3)">No rooms yet — add them in the property inventory.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
