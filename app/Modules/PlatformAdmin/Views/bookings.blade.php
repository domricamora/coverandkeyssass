<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Bookings', 'sub' => 'Every stay booked on the platform.'])

    <form method="GET" class="admin-search mt-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Reference, guest name or email…" class="form-input" aria-label="Search bookings" />
        <select name="status" class="form-input" aria-label="Status" style="max-width:160px">
            <option value="">Any status</option>
            @foreach ($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach
        </select>
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
    </form>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">Reference</th><th scope="col">Business / property</th><th scope="col">Guest</th><th scope="col">Dates</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td><strong>{{ $booking->reference }}</strong><br><small style="color:var(--text-3)">{{ $booking->created_at->format('M j, Y') }}</small></td>
                        <td>{{ $booking->tenant?->name }}<br><small>{{ $booking->property?->name }}</small></td>
                        <td>{{ $booking->guest_name }}<br><small>{{ $booking->guest_email }}</small></td>
                        <td>{{ $booking->check_in?->format('M j') }} – {{ $booking->check_out?->format('M j, Y') }}</td>
                        <td>{{ $booking->currency }} {{ number_format((float) $booking->total, 2) }}</td>
                        <td>{{ str_replace('_', ' ', $booking->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:var(--text-3)">No bookings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $bookings->links() }}
</x-app-layout>
