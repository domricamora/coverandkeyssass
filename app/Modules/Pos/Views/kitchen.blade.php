<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Kitchen — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $orders->count() }} ticket(s) in the kitchen · online, room service and dine-in.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('pos.kitchen', $restaurant) }}" class="btn btn-ghost">Refresh</a>
            <a href="{{ route('pos.register', $restaurant) }}" class="btn btn-ghost">Register</a>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mt-4">
        @forelse ($orders as $order)
            <div class="card p-4 text-sm" style="{{ $order->status === 'preparing' ? 'border-color:var(--accent, #b45309)' : '' }}">
                <div class="flex justify-between">
                    <strong style="color:var(--text)">{{ $order->table ? 'Table '.$order->table->label : $order->fulfillmentLabel() }}</strong>
                    <span class="badge {{ $order->status === 'preparing' ? 'badge-amber' : 'badge-gray' }}">{{ $order->statusLabel() }}</span>
                </div>
                <small style="color:var(--text-3)">{{ $order->reference }} · {{ ($order->accepted_at ?? $order->created_at)->diffForHumans() }}{{ $order->scheduled_for ? ' · for '.$order->scheduled_for->format('g:i A') : '' }}</small>
                <ul class="mt-2 space-y-1">
                    @foreach ($order->items as $item)
                        <li><strong>{{ $item->quantity }}×</strong> {{ $item->name }}@if ($item->modifiers) <small>({{ $item->modifierLabel() }})</small>@endif @if ($item->notes)<br><em>{{ $item->notes }}</em>@endif</li>
                    @endforeach
                </ul>
                @if ($order->notes)<p class="mt-1"><em>{{ $order->notes }}</em></p>@endif
                <form method="POST" action="{{ route('pos.kitchen.bump', [$restaurant, $order->reference]) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary w-full">{{ $order->status === 'accepted' ? 'Start' : 'Ready' }}</button>
                </form>
            </div>
        @empty
            <div class="card p-6 text-sm" style="color:var(--text-3)">Nothing cooking.</div>
        @endforelse
    </div>
</x-app-layout>
