<x-public-layout :title="'Trip '.$booking->reference" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Trip {{ $booking->reference }}</span>
            <h1>{{ $booking->property?->name }}</h1>
            <p><span class="badge {{ $booking->badge() }}">{{ $booking->statusLabel() }}</span>
                {{ $booking->check_in->format('D, M j') }} – {{ $booking->check_out->format('D, M j, Y') }} ·
                {{ $booking->nights() }} {{ Str::plural('night', $booking->nights()) }} ·
                {{ $booking->adults }} adults{{ $booking->children ? ', '.$booking->children.' children' : '' }}</p>
        </div>

        @include('customer::partials.nav')

        @if ($booking->status === 'pending')
            <div class="card" style="padding:14px;margin-bottom:16px;background:var(--surface-2);">
                <p class="muted" style="margin:0;">Not confirmed yet — the rooms are held for you until the booking is paid or the host confirms it.</p>
            </div>
        @endif

        @isset($payments)
            @include('payments::partials.panel', ['guest' => true])
        @endisset

        <div class="card" style="padding:18px;margin-bottom:16px;">
            <h2 style="font-size:1.1rem;margin-top:0;">Rooms &amp; price</h2>
            <div class="price-breakdown">
                @foreach ($booking->rooms as $line)
                    <p><span>{{ $line->roomType?->name }}</span> <strong>{{ $booking->money($line->total) }}</strong></p>
                @endforeach
                @if ((float) $booking->discount_total > 0)
                    <p><span>Discount{{ $booking->promotion ? ' ('.$booking->promotion->code.')' : '' }}</span> <strong>− {{ $booking->money($booking->discount_total) }}</strong></p>
                @endif
                <p class="price-breakdown__total"><span>Total</span> <span>{{ $booking->money($booking->total) }}</span></p>
            </div>
            <p style="margin-bottom:0;"><a href="{{ route('account.bookings.invoice', $booking->reference) }}">View invoice</a>
                @if ($booking->property?->check_in_time) · Check-in from {{ $booking->property->check_in_time }}, check-out by {{ $booking->property->check_out_time }} @endif
            </p>
        </div>

        @error('booking') <p class="muted" style="color:#b42318;">{{ $message }}</p> @enderror

        @if ($booking->guestCancellable())
            <form method="POST" action="{{ route('account.bookings.cancel', $booking->reference) }}" onsubmit="return confirm('Cancel this booking?')">
                @csrf
                <button class="btn btn-outline" type="submit">Cancel booking</button>
            </form>
            @if (! empty($booking->property?->policies['cancellation']))
                <p class="muted">Cancellation policy: {{ $booking->property->policies['cancellation'] }}</p>
            @endif
        @endif

        @if ($booking->reviewable() && ! $hasReviewed)
            <form method="POST" action="{{ route('account.bookings.review', $booking->reference) }}" class="card" style="padding:18px;display:grid;gap:8px;max-width:560px;">
                @csrf
                <h2 style="font-size:1.1rem;margin:0;">How was your stay?</h2>
                <label>Rating
                    <select class="form-input" name="rating">
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ $i }} {{ Str::plural('star', $i) }}</option>
                        @endfor
                    </select>
                </label>
                <label>Title <input class="form-input" name="title" value="{{ old('title') }}"></label>
                <label>Review <textarea class="form-input" name="comment" rows="4" required>{{ old('comment') }}</textarea></label>
                @error('rating') <p class="muted" style="color:#b42318;margin:0;">{{ $message }}</p> @enderror
                @error('comment') <p class="muted" style="color:#b42318;margin:0;">{{ $message }}</p> @enderror
                <button class="btn btn-primary" type="submit">Publish review</button>
            </form>
        @endif
    </div>
</x-public-layout>
