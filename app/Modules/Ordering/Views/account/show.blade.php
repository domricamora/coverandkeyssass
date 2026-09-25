<x-public-layout :title="'Order '.$order->reference" :show-search="false">
    <div class="container section" style="max-width:860px;">
        <div class="section-head">
            <span class="eyebrow">Order {{ $order->reference }}</span>
            <h1>{{ $order->restaurant?->name ?? 'Restaurant' }}</h1>
            <p class="muted">{{ $order->created_at->format('M j, Y · g:i A') }} · {{ $order->fulfillmentLabel() }} · {{ $order->paymentLabel() }}</p>
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

        @if ($order->status === 'completed' && ! $reviewed)
            <form method="POST" action="{{ route('account.orders.review', $order->reference) }}" class="card" style="padding:18px;display:grid;gap:8px;margin-top:16px;">
                @csrf
                <h2 style="font-size:1.1rem;margin:0;">How was your meal?</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;">
                    @foreach (['rating' => 'Overall', 'rating_food' => 'Food', 'rating_service' => 'Service', 'rating_value' => 'Value'] as $field => $label)
                        <label>{{ $label }}
                            <select class="form-input" name="{{ $field }}" @if ($field === 'rating') required @endif>
                                @if ($field !== 'rating')<option value="">—</option>@endif
                                @for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }}</option>@endfor
                            </select>
                        </label>
                    @endforeach
                </div>
                @foreach ($items->whereNotNull('menu_item_id')->unique('menu_item_id') as $line)
                    <label>{{ $line->name }}
                        <select class="form-input" name="items[{{ $line->menu_item_id }}]"><option value="">—</option>@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} ★</option>@endfor</select>
                    </label>
                @endforeach
                <textarea class="form-input" name="comment" rows="3" required placeholder="Tell others what you liked" aria-label="Review">{{ old('comment') }}</textarea>
                @error('rating')<p class="muted" style="color:#b42318;margin:0;">{{ $message }}</p>@enderror
                <button class="btn btn-primary" type="submit">Publish review</button>
            </form>
        @endif
    </div>
</x-public-layout>
