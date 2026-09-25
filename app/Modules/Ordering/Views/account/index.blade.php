<x-public-layout title="My orders" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Orders</h1>
        </div>

        @include('customer::partials.nav')

        @forelse ($orders as $order)
            <a href="{{ route('account.orders.show', $order->reference) }}" class="card" style="padding:16px;margin-bottom:12px;display:flex;justify-content:space-between;gap:16px;align-items:center;color:inherit;text-decoration:none;">
                <div>
                    <strong>{{ $order->restaurant?->name ?? 'Restaurant' }}</strong>
                    <p class="muted" style="margin:4px 0 0;">{{ $order->created_at->format('M j, Y · g:i A') }} · {{ ucfirst($order->fulfillment) }} · {{ $order->reference }}</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <span class="chip-inline">{{ $order->statusLabel() }}</span>
                    <strong>{{ $order->money($order->total) }}</strong>
                </div>
            </a>
        @empty
            <p class="muted">No orders yet.</p>
        @endforelse

        {{ $orders->links('marketplace::partials.pagination') }}
    </div>
</x-public-layout>
