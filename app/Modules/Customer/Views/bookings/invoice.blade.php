<x-public-layout :title="'Invoice '.$booking->reference" :show-search="false">
    <div class="container section" style="max-width:760px;">
        <div class="flex justify-between items-start" style="margin-bottom:20px;">
            <div>
                <span class="eyebrow">Invoice</span>
                <h1 style="margin:4px 0;">{{ $booking->reference }}</h1>
                <p class="muted" style="margin:0;">Issued {{ $booking->created_at->format('M j, Y') }} · {{ $booking->statusLabel() }}</p>
            </div>
            <button class="btn btn-ghost" type="button" onclick="window.print()">Print</button>
        </div>

        <div class="card" style="padding:18px;">
            <p style="margin-top:0;"><strong>{{ $booking->property?->name }}</strong><br><span class="muted">{{ $booking->property?->locationLabel() }}</span></p>
            <p><strong>Billed to</strong><br>{{ $booking->guest_name }}<br><span class="muted">{{ $booking->guest_email }}</span></p>

            <table class="table">
                <thead><tr><th>Item</th><th>Nights</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @foreach ($booking->rooms as $line)
                        <tr>
                            <td>{{ $line->roomType?->name }}</td>
                            <td>{{ count($line->nightly_rates) }}</td>
                            <td class="text-right">{{ $booking->money($line->total) }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="2">Subtotal</td><td class="text-right">{{ $booking->money($booking->subtotal) }}</td></tr>
                    @if ((float) $booking->discount_total > 0)
                        <tr><td colspan="2">Discount{{ $booking->promotion ? ' ('.$booking->promotion->code.')' : '' }}</td><td class="text-right">− {{ $booking->money($booking->discount_total) }}</td></tr>
                    @endif
                    <tr><td colspan="2"><strong>Total</strong></td><td class="text-right"><strong>{{ $booking->money($booking->total) }}</strong></td></tr>
                </tbody>
            </table>
            <p class="muted" style="margin-bottom:0;">Payments made online are listed on the trip page and under Payments in your account.</p>
        </div>
    </div>
</x-public-layout>
