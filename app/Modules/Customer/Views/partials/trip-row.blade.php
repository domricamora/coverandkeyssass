<a class="card" href="{{ route('account.bookings.show', $booking->reference) }}" style="display:flex;justify-content:space-between;gap:12px;padding:14px 16px;margin-bottom:10px;color:inherit;">
    <span>
        <strong style="display:block;color:var(--text);">{{ $booking->property?->name ?? 'Property' }}</strong>
        <span class="muted">{{ $booking->check_in->format('M j') }} – {{ $booking->check_out->format('M j, Y') }} · {{ $booking->reference }}</span>
    </span>
    <span style="text-align:right;">
        <span class="badge {{ $booking->badge() }}">{{ $booking->statusLabel() }}</span>
        <span class="muted" style="display:block;">{{ $booking->money($booking->total) }}</span>
    </span>
</a>
