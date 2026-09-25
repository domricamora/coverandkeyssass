@php
    use App\Modules\Ordering\Models\Order;
    $canManage = auth()->user()->hasPermissionTo('orders.manage');
    $stages = [Order::PENDING => 'New', Order::ACCEPTED => 'Accepted', Order::PREPARING => 'Preparing', Order::READY => 'Ready', Order::OUT_FOR_DELIVERY => 'Out for delivery', Order::DELIVERED => 'Delivered'];
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Orders — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                Online ordering is {{ $restaurant->ordering_enabled ? 'on' : 'off' }}.
                @unless ($restaurant->ordering_enabled) Turn it on in the <a href="{{ route('restaurants.edit', $restaurant) }}">profile</a>. @endunless
            </p>
        </div>
        <a href="{{ route('restaurants.show', $restaurant) }}" class="btn btn-ghost">Back</a>
    </div>

    <x-input-error :messages="$errors->get('status')" />

    <div class="grid grid-cols-3 gap-4 mt-4">
        @foreach ($stages as $status => $label)
            <div class="card p-4">
                <h2 class="text-lg">{{ $label }} <small style="color:var(--text-3)">{{ $open->get($status, collect())->count() }}</small></h2>
                @forelse ($open->get($status, collect()) as $order)
                    <div class="mt-3 pt-3 text-sm" style="border-top:1px solid var(--border);color:var(--text-2)">
                        <a href="{{ route('restaurants.orders.show', [$restaurant, $order->reference]) }}"><strong style="color:var(--text)">{{ $order->reference }}</strong></a>
                        · {{ $order->customer_name }} · {{ (int) $order->items_sum_quantity }} item(s) · {{ $order->money($order->total) }}
                        <br><small style="color:var(--text-3)">{{ ucfirst($order->fulfillment) }} · {{ $order->payment_method === 'online' ? 'online '.$order->payment_status : 'cash' }} · {{ $order->created_at->diffForHumans() }}</small>
                        @if ($canManage)
                            <div class="flex gap-1 mt-2">
                                @foreach ($order->nextStates() as $to)
                                    <form method="POST" action="{{ route('restaurants.orders.transition', [$restaurant, $order->reference]) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $to }}" />
                                        <button type="submit" class="btn btn-sm {{ $to === Order::CANCELLED ? 'btn-danger' : 'btn-primary' }}">{{ \Illuminate\Support\Str::headline($to) }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="mt-2 text-sm" style="color:var(--text-3)">—</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-3 gap-4 mt-6">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse ($recent as $order)
                        <tr>
                            <td><a href="{{ route('restaurants.orders.show', [$restaurant, $order->reference]) }}">{{ $order->reference }}</a><br><small style="color:var(--text-3)">{{ $order->created_at->format('M j, g:i A') }}</small></td>
                            <td style="color:var(--text-2)">{{ $order->customer_name }}</td>
                            <td><span class="badge {{ $order->status === Order::COMPLETED ? 'badge-green' : 'badge-gray' }}">{{ $order->statusLabel() }}</span></td>
                            <td class="text-right">{{ $order->money($order->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-sm" style="color:var(--text-3)">No finished orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card p-6">
            <h2 class="text-lg">Order promo codes</h2>
            <ul class="mt-2 text-sm space-y-2" style="color:var(--text-2)">
                @forelse ($promotions as $promo)
                    <li class="flex justify-between items-center">
                        <span><strong>{{ $promo->code }}</strong> · {{ $promo->label() }} @if ($promo->min_subtotal) · min {{ number_format((float) $promo->min_subtotal, 0) }} @endif · used {{ $promo->used_count }}{{ $promo->max_uses ? '/'.$promo->max_uses : '' }}</span>
                        @if (auth()->user()->hasPermissionTo('promotions.manage') && $promo->restaurant_id)
                            <form method="POST" action="{{ route('restaurants.orders.promotions.toggle', [$restaurant, $promo->id]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost">{{ $promo->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li style="color:var(--text-3)">No order promo codes yet.</li>
                @endforelse
            </ul>
            @if (auth()->user()->hasPermissionTo('promotions.manage'))
                <form method="POST" action="{{ route('restaurants.orders.promotions.store', $restaurant) }}" class="mt-4 space-y-2">
                    @csrf
                    <input name="code" type="text" required class="form-input" placeholder="LUNCH10" aria-label="Code" />
                    <input name="name" type="text" required class="form-input" placeholder="Lunch promo" aria-label="Name" />
                    <div class="flex gap-2">
                        <select name="type" class="form-input" aria-label="Type"><option value="percent">% off</option><option value="fixed">₱ off</option></select>
                        <input name="value" type="number" step="0.01" min="0.01" required class="form-input" placeholder="10" aria-label="Value" />
                    </div>
                    <div class="flex gap-2">
                        <input name="min_subtotal" type="number" step="0.01" min="0" class="form-input" placeholder="Min order" aria-label="Minimum order" />
                        <input name="max_uses" type="number" min="1" class="form-input" placeholder="Max uses" aria-label="Maximum uses" />
                    </div>
                    <x-input-error :messages="$errors->get('code')" />
                    <x-input-error :messages="$errors->get('value')" />
                    <button type="submit" class="btn btn-primary w-full">Create code</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
