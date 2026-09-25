<x-public-layout :title="'Order '.$order->reference" :show-search="false">
    <div class="container section" style="max-width:860px;">
        <div class="section-head">
            <span class="eyebrow">Order {{ $order->reference }}</span>
            <h1>{{ $order->restaurant?->name ?? 'Restaurant' }}</h1>
            <p class="muted">{{ $order->created_at->format('M j, Y · g:i A') }} · {{ ucfirst($order->fulfillment) }} · {{ $order->payment_method === 'online' ? 'Paid online' : 'Cash' }}</p>
        </div>

        @include('customer::partials.nav')

        @error('order')<p role="alert" class="card" style="padding:12px;color:var(--danger, #b91c1c);">{{ $message }}</p>@enderror
        @error('payment')<p role="alert" class="card" style="padding:12px;color:var(--danger, #b91c1c);">{{ $message }}</p>@enderror

        <div class="card" style="padding:16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;gap:12px;">
            <div>
                <strong>Status: {{ $order->statusLabel() }}</strong>
                <p class="muted" style="margin:4px 0 0;">Payment: {{ ucfirst($order->payment_status) }}</p>
                @if ($order->delivery_address)<p class="muted" style="margin:4px 0 0;">Deliver to: {{ $order->delivery_address }}</p>@endif
                @if ($order->scheduled_for)<p class="muted" style="margin:4px 0 0;">Scheduled for {{ $order->scheduled_for->format('D, M j · g:i A') }}</p>@endif
                @if ($order->estimated_at && in_array($order->status, \App\Modules\Ordering\Models\Order::OPEN, true))
                    <p style="margin:4px 0 0;"><strong>Estimated {{ $order->fulfillment === 'delivery' ? 'arrival' : 'ready' }}: {{ $order->estimated_at->format('g:i A') }}</strong></p>
                @endif
                @if ($order->status === 'out_for_delivery' && $order->driver)
                    <p class="muted" style="margin:4px 0 0;">On the way with {{ $order->driver->name }}{{ $order->driver->phone ? ' · '.$order->driver->phone : '' }}</p>
                @endif
                @if ($order->delivered_at)<p class="muted" style="margin:4px 0 0;">Delivered {{ $order->delivered_at->format('g:i A') }}</p>@endif
            </div>
            <div style="display:flex;gap:8px;">
                @if ($order->needsPayment() && $onlinePayments)
                    <form method="POST" action="{{ route('account.orders.pay', $order->reference) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Pay {{ $order->money($order->total) }}</button>
                    </form>
                @endif
                @if ($order->status === 'pending')
                    <form method="POST" action="{{ route('account.orders.cancel', $order->reference) }}" onsubmit="return confirm('Cancel this order?')">
                        @csrf
                        <button class="btn btn-ghost" type="submit">Cancel order</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card" style="padding:16px;">
            @include('ordering::partials.summary')
        </div>
    </div>
</x-public-layout>
