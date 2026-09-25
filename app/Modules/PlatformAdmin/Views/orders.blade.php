<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Food orders', 'sub' => 'Online, room service and POS orders across restaurants.'])

    <form method="GET" class="admin-search mt-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Reference or customer…" class="form-input" aria-label="Search orders" />
        <select name="status" class="form-input" aria-label="Status" style="max-width:180px">
            <option value="">Any status</option>
            @foreach (['pending', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'refunded'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
    </form>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">Reference</th><th scope="col">Restaurant</th><th scope="col">Customer</th><th scope="col">Type</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><strong>{{ $order->reference }}</strong><br><small style="color:var(--text-3)">{{ $order->created_at->format('M j, Y g:i A') }}</small></td>
                        <td>{{ $order->restaurant?->name }}</td>
                        <td>{{ $order->customer_name }}</td>
                        <td>{{ $order->channel }} · {{ str_replace('_', ' ', $order->fulfillment) }}<br><small>{{ $order->payment_method }} / {{ $order->payment_status }}</small></td>
                        <td>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                        <td>{{ str_replace('_', ' ', $order->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:var(--text-3)">No orders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
</x-app-layout>
