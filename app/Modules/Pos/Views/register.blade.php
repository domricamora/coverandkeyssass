<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>POS — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                @if ($session)
                    Register open since {{ $session->opened_at->format('g:i A') }} · float ₱{{ number_format((float) $session->opening_float, 2) }} · cash in drawer ₱{{ number_format($session->cashInDrawer(), 2) }}
                @else
                    Register closed — open it to take payments.
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('pos.kitchen', $restaurant) }}" class="btn btn-ghost">Kitchen display</a>
            <a href="{{ route('restaurants.show', $restaurant) }}" class="btn btn-ghost">Restaurant</a>
        </div>
    </div>

    @foreach (['session', 'item_id', 'modifiers', 'cart', 'lines', 'restaurant_table_id', 'counted_cash', 'opening_float'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="space-y-4" style="grid-column:span 2">
            <div class="card p-6">
                <h2 class="text-lg">Tables</h2>
                <div class="grid grid-cols-4 gap-2 mt-2">
                    @foreach ($tables as $table)
                        @php($ticket = $open->firstWhere('restaurant_table_id', $table->id))
                        @if ($ticket)
                            <a href="{{ route('pos.tickets.show', [$restaurant, $ticket->reference]) }}" class="card p-3 text-sm" style="border-color:var(--accent, #b45309)">
                                <strong>{{ $table->label }}</strong> · {{ $table->seats }} seats<br><span class="badge badge-amber">{{ $ticket->statusLabel() }} · {{ $ticket->money($ticket->total) }}</span>
                            </a>
                        @else
                            <div class="card p-3 text-sm" style="color:var(--text-3)"><strong style="color:var(--text)">{{ $table->label }}</strong> · {{ $table->seats }} seats<br>Free</div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-lg">Open tickets</h2>
                <ul class="mt-2 text-sm space-y-2">
                    @forelse ($open as $ticket)
                        <li class="flex justify-between">
                            <a href="{{ route('pos.tickets.show', [$restaurant, $ticket->reference]) }}"><strong>{{ $ticket->reference }}</strong> · {{ $ticket->customer_name }}</a>
                            <span>{{ $ticket->statusLabel() }} · {{ $ticket->money($ticket->total) }} · {{ ucfirst($ticket->payment_status) }}</span>
                        </li>
                    @empty
                        <li style="color:var(--text-3)">No open tickets.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            @if ($menu->isNotEmpty())
                <form method="POST" action="{{ route('pos.tickets.store', $restaurant) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">New ticket</h2>
                    <select name="restaurant_table_id" class="form-input" aria-label="Table">
                        <option value="">Takeaway / counter</option>
                        @foreach ($tables as $table)
                            @unless (in_array($table->id, $busyTables, true))<option value="{{ $table->id }}">{{ $table->label }} ({{ $table->seats }})</option>@endunless
                        @endforeach
                    </select>
                    <input name="customer_name" type="text" class="form-input" placeholder="Guest name (optional)" aria-label="Guest name" />
                    @include('pos::partials.line-form')
                    <button type="submit" class="btn btn-primary w-full">Send to kitchen</button>
                </form>
            @endif

            @if ($session)
                @if (auth()->user()->hasPermissionTo('pos.manage'))
                    <form method="POST" action="{{ route('pos.sessions.close', [$restaurant, $session->id]) }}" class="card p-6 space-y-2">
                        @csrf
                        <h2 class="text-lg">Close the day</h2>
                        <p class="text-sm" style="color:var(--text-3)">Expected in drawer: ₱{{ number_format($session->cashInDrawer(), 2) }}</p>
                        <input name="counted_cash" type="number" step="0.01" min="0" required class="form-input" placeholder="Counted cash ₱" aria-label="Counted cash" />
                        <input name="notes" type="text" class="form-input" placeholder="Notes" aria-label="Notes" />
                        <button type="submit" class="btn btn-dark w-full">Count & close</button>
                    </form>
                @endif
            @else
                <form method="POST" action="{{ route('pos.sessions.open', $restaurant) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Open the register</h2>
                    <input name="opening_float" type="number" step="0.01" min="0" value="2000" required class="form-input" aria-label="Opening float" />
                    <button type="submit" class="btn btn-primary w-full">Open</button>
                </form>
            @endif

            @if ($sessions->isNotEmpty() && auth()->user()->hasPermissionTo('pos.manage'))
                <div class="card p-6 text-sm">
                    <h2 class="text-lg">Past closings</h2>
                    <ul class="mt-2 space-y-1">
                        @foreach ($sessions as $past)
                            <li><a href="{{ route('pos.sessions.show', [$restaurant, $past->id]) }}">{{ $past->closed_at->format('M j, g:i A') }}</a> · variance ₱{{ number_format((float) $past->variance, 2) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
