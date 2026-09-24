<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Bookings</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Reservations, walk-ins and group stays across your properties.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('bookings.calendar') }}" class="btn btn-ghost">Calendar</a>
            @if (auth()->user()->hasPermissionTo('promotions.manage'))
                <a href="{{ route('bookings.promotions.index') }}" class="btn btn-ghost">Promotions</a>
            @endif
            @if (auth()->user()->hasPermissionTo('bookings.create'))
                <a href="{{ route('bookings.create') }}" class="btn btn-primary">+ New booking</a>
            @endif
        </div>
    </div>

    <form method="GET" class="card p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label for="f_q" class="form-label">Search</label>
            <input id="f_q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input" placeholder="Reference, guest or group" />
        </div>
        <div>
            <label for="f_status" class="form-label">Status</label>
            <select id="f_status" name="status" class="form-input">
                <option value="">Any</option>
                @foreach (\App\Modules\Booking\Models\Booking::statuses() as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ Str::headline($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f_from" class="form-label">Staying from</label>
            <input id="f_from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input" />
        </div>
        <div>
            <label for="f_to" class="form-label">to</label>
            <input id="f_to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input" />
        </div>
        <button class="btn btn-dark" type="submit">Filter</button>
    </form>

    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th>Reference</th><th>Guest</th><th>Property</th><th>Stay</th><th>Status</th><th class="text-right">Total</th></tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td><a href="{{ route('bookings.show', $booking->reference) }}"><strong style="color:var(--text)">{{ $booking->reference }}</strong></a>
                            <br><small style="color:var(--text-3)">{{ Str::headline($booking->source) }}</small></td>
                        <td>{{ $booking->guest_name }}@if ($booking->group_name)<br><small style="color:var(--text-3)">{{ $booking->group_name }}</small>@endif</td>
                        <td style="color:var(--text-2)">{{ $booking->property?->name }}</td>
                        <td style="color:var(--text-2)">{{ $booking->check_in->format('M j') }} – {{ $booking->check_out->format('M j, Y') }}</td>
                        <td><span class="badge {{ $booking->badge() }}">{{ $booking->statusLabel() }}</span></td>
                        <td class="text-right">{{ $booking->money($booking->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-3)">No bookings match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->links() }}</div>
</x-app-layout>
