<x-public-layout title="Payments" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Payments</h1>
        </div>

        @include('customer::partials.nav')

        <div class="table-wrap card">
            <table class="table">
                <thead><tr><th>Date</th><th>Booking</th><th>Method</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ ($payment->paid_at ?? $payment->created_at)->format('M j, Y') }}</td>
                            <td>
                                @if ($payment->booking)
                                    <a href="{{ route('account.bookings.show', $payment->booking->reference) }}">{{ $payment->booking->reference }}</a>
                                @endif
                            </td>
                            <td>{{ $payment->method ? Str::headline($payment->method) : 'PayMongo' }}</td>
                            <td><span class="badge {{ $payment->badge() }}">{{ ucfirst($payment->status) }}</span></td>
                            <td class="text-right">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted" style="text-align:center;padding:24px;">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $payments->links('marketplace::partials.pagination') }}
    </div>
</x-public-layout>
