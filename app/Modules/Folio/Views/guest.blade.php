<x-public-layout :title="'Folio '.$booking->reference" :show-search="false">
    <div class="container section" style="max-width:860px;">
        <div class="section-head">
            <span class="eyebrow">Stay {{ $booking->reference }}</span>
            <h1>Your folio</h1>
            <p class="muted">{{ $booking->property?->name }} · {{ $booking->check_in->format('M j') }}–{{ $booking->check_out->format('M j, Y') }}</p>
        </div>

        @include('customer::partials.nav')

        <div class="card" style="padding:16px;">
            @include('folio::partials.ledger', ['voidable' => false])
        </div>
        <p class="muted" style="margin-top:12px;"><a href="{{ route('account.bookings.show', $booking->reference) }}">Back to your trip</a></p>
    </div>
</x-public-layout>
