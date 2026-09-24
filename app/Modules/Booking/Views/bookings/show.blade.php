@php($actions = [
    'confirmed' => ['Confirm', 'btn-primary'],
    'checked_in' => ['Check in', 'btn-primary'],
    'checked_out' => ['Check out', 'btn-dark'],
    'completed' => ['Mark completed', 'btn-dark'],
    'no_show' => ['No-show', 'btn-ghost'],
    'refunded' => ['Mark refunded', 'btn-ghost'],
    'held' => ['Hold', 'btn-ghost'],
])

<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $booking->reference }} <span class="badge {{ $booking->badge() }}">{{ $booking->statusLabel() }}</span></h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $booking->property?->name }} · {{ $booking->check_in->format('D, M j') }} – {{ $booking->check_out->format('D, M j, Y') }}
                · {{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }} · {{ Str::headline($booking->source) }}
            </p>
        </div>
        <a href="{{ route('bookings.index') }}" class="btn btn-ghost">Back to bookings</a>
    </div>

    <x-input-error :messages="$errors->get('status')" />

    @if (auth()->user()->hasPermissionTo('bookings.update'))
        <div class="card p-4 mb-4 flex flex-wrap gap-2 items-center">
            @foreach (\App\Modules\Booking\Models\Booking::TRANSITIONS[$booking->status] as $to)
                @if ($to === 'cancelled')
                    <form method="POST" action="{{ route('bookings.transition', $booking->reference) }}" class="flex gap-2" onsubmit="return confirm('Cancel this booking and release its rooms?')">
                        @csrf
                        <input type="hidden" name="status" value="cancelled">
                        <input name="reason" class="form-input" placeholder="Cancellation reason" aria-label="Cancellation reason">
                        <button type="submit" class="btn btn-danger">Cancel booking</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('bookings.transition', $booking->reference) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $to }}">
                        <button type="submit" class="btn {{ $actions[$to][1] ?? 'btn-ghost' }}">{{ $actions[$to][0] ?? Str::headline($to) }}</button>
                    </form>
                @endif
            @endforeach
            @if ($booking->hold_expires_at)
                <span class="text-sm" style="color:var(--text-3)">Hold expires {{ $booking->hold_expires_at->diffForHumans() }}</span>
            @endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <h2 class="text-lg">Guest</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt style="color:var(--text-3)">Name</dt><dd>{{ $booking->guest_name }}</dd></div>
                <div class="flex justify-between"><dt style="color:var(--text-3)">Email</dt><dd>{{ $booking->guest_email ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt style="color:var(--text-3)">Phone</dt><dd>{{ $booking->guest_phone ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt style="color:var(--text-3)">Guests</dt><dd>{{ $booking->adults }} adults, {{ $booking->children }} children</dd></div>
                @if ($booking->group_name)
                    <div class="flex justify-between"><dt style="color:var(--text-3)">Group</dt><dd>{{ $booking->group_name }}</dd></div>
                @endif
                @if ($booking->special_requests)
                    <div><dt style="color:var(--text-3)">Special requests</dt><dd>{{ $booking->special_requests }}</dd></div>
                @endif
                @if ($booking->cancellation_reason)
                    <div class="flex justify-between"><dt style="color:var(--text-3)">Cancellation</dt><dd>{{ $booking->cancellation_reason }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="card p-6">
            <h2 class="text-lg">Rooms &amp; charges</h2>
            <table class="table mt-3">
                <tbody>
                    @foreach ($booking->rooms as $line)
                        <tr>
                            <td>{{ $line->roomType?->name }} · Room {{ $line->room?->room_number }}</td>
                            <td class="text-right">{{ $booking->money($line->total) }}</td>
                        </tr>
                    @endforeach
                    <tr><td>Subtotal</td><td class="text-right">{{ $booking->money($booking->subtotal) }}</td></tr>
                    @if ((float) $booking->discount_total > 0)
                        <tr><td>Discount{{ $booking->promotion ? ' ('.$booking->promotion->code.')' : '' }}</td><td class="text-right">− {{ $booking->money($booking->discount_total) }}</td></tr>
                    @endif
                    <tr><td><strong>Total</strong></td><td class="text-right"><strong>{{ $booking->money($booking->total) }}</strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
