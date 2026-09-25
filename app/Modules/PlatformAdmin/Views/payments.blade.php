<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Payments & refunds', 'sub' => 'PayMongo payments from guests. A refund cancels the booking or order first when needed, then refunds through PayMongo.'])

    <div class="flex gap-2 mt-4">
        @foreach (['' => 'All', 'paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $key => $label)
            <a href="{{ route('admin.payments.index', array_filter(['status' => $key])) }}" class="btn btn-sm {{ (string) request('status') === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">Payment</th><th scope="col">For</th><th scope="col">Payer</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Refund</th></tr></thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>#{{ $payment->id }}<br><small style="color:var(--text-3)">{{ $payment->created_at->format('M j, Y') }} · {{ $payment->method ?? '—' }}</small></td>
                        <td>{{ $payment->booking ? 'Booking '.$payment->booking->reference : ($payment->order ? 'Order '.$payment->order->reference : '—') }}</td>
                        <td>{{ $payment->user?->name }}<br><small>{{ $payment->user?->email }}</small></td>
                        <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ $payment->status }}{{ $payment->refunded_at ? ' '.$payment->refunded_at->format('M j') : '' }}</td>
                        <td>
                            @if ($payment->status === 'paid')
                                <form method="POST" action="{{ route('admin.payments.refund', $payment->id) }}" class="flex gap-1">
                                    @csrf
                                    <input name="reason" required class="form-input" placeholder="Reason" aria-label="Refund reason for payment {{ $payment->id }}" style="max-width:170px" />
                                    <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('Cancel and refund this payment in full?')">Refund</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:var(--text-3)">No payments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $payments->links() }}
</x-app-layout>
