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
    <x-input-error :messages="$errors->get('driver_id')" />

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="card p-6" style="grid-column:span 2">
            @include('ordering::partials.summary', ['items' => $order->items])
        </div>
        <div class="card p-6 text-sm space-y-2" style="color:var(--text-2)">
            <p><span class="badge badge-amber">{{ $order->statusLabel() }}</span></p>
            <p><strong>{{ $order->customer_name }}</strong><br>{{ $order->customer_phone }}</p>
            @if ($order->delivery_address)
                <p><strong>Deliver to</strong> ({{ $order->zone?->name ?? 'zone removed' }})<br>{{ $order->delivery_address }}
                    @if ($order->delivery_lat)<br><a href="https://maps.google.com/?q={{ $order->delivery_lat }},{{ $order->delivery_lng }}" target="_blank" rel="noopener">Open pin in maps</a>@endif
                </p>
            @endif
            @if ($order->scheduled_for)<p><strong>Scheduled</strong> {{ $order->scheduled_for->format('D, M j · g:i A') }}</p>@endif
            @if ($order->estimated_at)<p><strong>ETA</strong> {{ $order->estimated_at->format('g:i A') }}</p>@endif
            @if ($order->fulfillment === 'delivery')
                <p><strong>Driver</strong> {{ $order->driver?->name ?? 'not assigned' }}{{ $order->driver?->phone ? ' · '.$order->driver->phone : '' }}</p>
                @if (auth()->user()->hasPermissionTo('orders.manage') && ! in_array($order->status, ['pending', 'delivered', 'completed', 'cancelled', 'refunded'], true))
                    <form method="POST" action="{{ route('restaurants.orders.driver', [$restaurant, $order->reference]) }}" class="flex gap-2">
                        @csrf
                        <select name="driver_id" class="form-input" aria-label="Driver">
                            <option value="">No driver</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected($order->driver_id === $driver->id)>{{ $driver->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-ghost">Save</button>
                    </form>
                @endif
            @endif
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
