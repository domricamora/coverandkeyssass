<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Order {{ $order->reference }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $order->created_at->format('M j, Y · g:i A') }} · {{ ucfirst($order->fulfillment) }} ·
                {{ $order->payment_method === 'online' ? 'Online, '.$order->payment_status : 'Cash' }}
            </p>
        </div>
        <a href="{{ route('restaurants.orders.index', $restaurant) }}" class="btn btn-ghost">Back to orders</a>
    </div>

    <x-input-error :messages="$errors->get('status')" />
    <x-input-error :messages="$errors->get('payment')" />

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="card p-6" style="grid-column:span 2">
            @include('ordering::partials.summary', ['items' => $order->items])
        </div>
        <div class="card p-6 text-sm space-y-2" style="color:var(--text-2)">
            <p><span class="badge badge-amber">{{ $order->statusLabel() }}</span></p>
            <p><strong>{{ $order->customer_name }}</strong><br>{{ $order->customer_phone }}</p>
            @if ($order->delivery_address)<p><strong>Deliver to</strong><br>{{ $order->delivery_address }}</p>@endif
            @if ($order->notes)<p><strong>Notes</strong><br>{{ $order->notes }}</p>@endif
            @if ($order->cancellation_reason)<p><strong>Cancelled</strong> — {{ $order->cancellation_reason }}</p>@endif
            @if (auth()->user()->hasPermissionTo('orders.manage'))
                <div class="flex flex-wrap gap-2 pt-2">
                    @foreach ($order->nextStates() as $to)
                        <form method="POST" action="{{ route('restaurants.orders.transition', [$restaurant, $order->reference]) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $to }}" />
                            <button type="submit" class="btn btn-sm {{ in_array($to, ['cancelled', 'refunded'], true) ? 'btn-danger' : 'btn-primary' }}">{{ \Illuminate\Support\Str::headline($to) }}</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
