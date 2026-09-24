<x-public-layout title="My trips" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Trips</h1>
        </div>

        @include('customer::partials.nav')

        <div class="flex gap-2" style="margin-bottom:16px;">
            <a class="btn btn-sm {{ $upcoming ? 'btn-dark' : 'btn-ghost' }}" href="{{ route('account.bookings.index') }}">Upcoming</a>
            <a class="btn btn-sm {{ $upcoming ? 'btn-ghost' : 'btn-dark' }}" href="{{ route('account.bookings.index', ['tab' => 'past']) }}">Past &amp; cancelled</a>
        </div>

        @forelse ($bookings as $booking)
            @include('customer::partials.trip-row')
        @empty
            <p class="muted">Nothing here yet.</p>
        @endforelse

        {{ $bookings->links('marketplace::partials.pagination') }}
    </div>
</x-public-layout>
