@php
    $user = auth()->user();
    $openTicket = $order->payment_status === 'unpaid' && in_array($order->status, ['accepted', 'preparing', 'ready'], true);
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $order->reference }} · {{ $order->table ? 'Table '.$order->table->label : 'Counter' }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $order->customer_name }} · {{ $order->statusLabel() }} · {{ $order->paymentLabel() }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('pos.tickets.receipt', [$restaurant, $order->reference]) }}" class="btn btn-ghost" target="_blank" rel="noopener">Receipt</a>
            <a href="{{ route('pos.register', $restaurant) }}" class="btn btn-ghost">Register</a>
        </div>
    </div>

    @foreach (['order', 'session', 'amount', 'tendered', 'method', 'booking_id', 'room_id', 'status', 'item_id', 'modifiers', 'cart', 'reason', 'value'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="space-y-4" style="grid-column:span 2">
            <div class="card p-6">
                @include('ordering::partials.summary', ['items' => $order->items])
                @if ($order->discount_reason)<p class="text-sm mt-2" style="color:var(--text-3)">Discount: {{ $order->discount_reason }}</p>@endif
                <p class="mt-3"><strong>Balance due: {{ $order->money(max(0, $due)) }}</strong></p>
            </div>

            <div class="card p-6">
                <h2 class="text-lg">Payments</h2>
                <ul class="mt-2 text-sm space-y-1">
                    @forelse ($payments as $p)
                        <li>{{ $p->created_at->format('g:i A') }} · {{ \Illuminate\Support\Str::headline($p->method) }} · {{ (float) $p->amount < 0 ? 'refund ' : '' }}{{ $order->money(abs((float) $p->amount)) }}
                            @if ($p->tendered) · tendered {{ $order->money($p->tendered) }}, change {{ $order->money($p->change_given) }}@endif
                            @if ($p->reference) · {{ $p->reference }}@endif · {{ $p->user?->name }}</li>
                    @empty
                        <li style="color:var(--text-3)">Nothing paid yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            @if ($openTicket)
                <form method="POST" action="{{ route('pos.tickets.lines', [$restaurant, $order->reference]) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Add to ticket</h2>
                    @include('pos::partials.line-form')
                    <button type="submit" class="btn btn-ghost w-full">Add</button>
                </form>

                @if ($user->hasPermissionTo('pos.discount'))
                    <form method="POST" action="{{ route('pos.tickets.discount', [$restaurant, $order->reference]) }}" class="card p-6 space-y-2">
                        @csrf
                        <h2 class="text-lg">Discount</h2>
                        <div class="flex gap-2">
                            <select name="type" class="form-input" aria-label="Discount type"><option value="percent">%</option><option value="fixed">₱</option></select>
                            <input name="value" type="number" step="0.01" min="0" required class="form-input" aria-label="Discount value" />
                        </div>
                        <input name="reason" type="text" required class="form-input" placeholder="Senior citizen, manager comp…" aria-label="Discount reason" />
                        <button type="submit" class="btn btn-ghost w-full">Apply</button>
                    </form>
                @endif

                @if ($session)
                    <form method="POST" action="{{ route('pos.tickets.pay', [$restaurant, $order->reference]) }}" class="card p-6 space-y-2" x-data="{ method: 'cash' }">
                        @csrf
                        <h2 class="text-lg">Take payment</h2>
                        <select name="method" x-model="method" class="form-input" aria-label="Payment method">
                            <option value="cash">Cash</option><option value="card">Card</option><option value="ewallet">GCash / Maya</option>
                        </select>
                        <input name="tendered" x-show="method === 'cash'" type="number" step="0.01" min="0.01" value="{{ $due > 0 ? $due : '' }}" class="form-input" placeholder="Cash tendered" aria-label="Cash tendered" />
                        <input name="amount" x-show="method !== 'cash'" type="number" step="0.01" min="0.01" value="{{ $due > 0 ? $due : '' }}" class="form-input" placeholder="Amount" aria-label="Amount" />
                        <input name="reference" x-show="method !== 'cash'" type="text" class="form-input" placeholder="Approval / reference" aria-label="Reference" />
                        <button type="submit" class="btn btn-primary w-full">Pay</button>
                    </form>

                    @if ($stays->isNotEmpty() && $payments->isEmpty())
                        <form method="POST" action="{{ route('pos.tickets.room', [$restaurant, $order->reference]) }}" class="card p-6 space-y-2">
                            @csrf
                            <h2 class="text-lg">Charge to room</h2>
                            <select name="room_stay" class="form-input" aria-label="In-house guest">
                                @foreach ($stays as $stay)
                                    @foreach ($stay->rooms as $br)
                                        <option value="{{ $stay->id }}:{{ $br->room_id }}">Room {{ $br->room?->room_number }} · {{ $stay->guest_name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-ghost w-full">Post to folio</button>
                        </form>
                    @endif
                @else
                    <p class="card p-4 text-sm" style="color:var(--text-3)">Open the register to take payments.</p>
                @endif

                @if ($payments->isEmpty())
                    <form method="POST" action="{{ route('pos.tickets.cancel', [$restaurant, $order->reference]) }}" onsubmit="return confirm('Void this ticket?')">
                        @csrf
                        <button type="submit" class="btn btn-danger w-full">Void ticket</button>
                    </form>
                @endif
            @endif

            @if (in_array($order->payment_status, ['paid', 'charged'], true) && ! in_array($order->status, ['completed', 'refunded', 'cancelled'], true))
                <form method="POST" action="{{ route('pos.tickets.close', [$restaurant, $order->reference]) }}">
                    @csrf
                    <button type="submit" class="btn btn-dark w-full">Close ticket</button>
                </form>
            @endif

            @if ($order->canTransitionTo('refunded') && $user->hasPermissionTo('pos.refund') && $session)
                <form method="POST" action="{{ route('pos.tickets.refund', [$restaurant, $order->reference]) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Refund</h2>
                    <select name="method" class="form-input" aria-label="Refund method"><option value="cash">Cash</option><option value="card">Card</option><option value="ewallet">E-wallet</option></select>
                    <input name="reason" type="text" required class="form-input" placeholder="Reason" aria-label="Refund reason" />
                    <button type="submit" class="btn btn-danger w-full">Refund {{ $order->money($order->total) }}</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
