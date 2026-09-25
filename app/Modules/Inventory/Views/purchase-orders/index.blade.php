<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Purchase orders</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Order from suppliers; receiving books stock at the ordered cost.</p>
        </div>
    </div>
    @include('inventory::partials.nav')

    @foreach (['lines', 'supplier_id', 'stock_location_id'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>PO</th><th>Supplier</th><th>Deliver to</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse ($orders as $po)
                        <tr>
                            <td><a href="{{ route('inventory.purchase-orders.show', $po->reference) }}"><strong>{{ $po->reference }}</strong></a><br><small style="color:var(--text-3)">{{ $po->created_at->format('M j') }}{{ $po->expected_on ? ' · due '.$po->expected_on->format('M j') : '' }}</small></td>
                            <td>{{ $po->supplier?->name }}</td>
                            <td>{{ $po->location?->name }}</td>
                            <td><span class="badge {{ $po->status === 'received' ? 'badge-green' : 'badge-gray' }}">{{ $po->statusLabel() }}</span></td>
                            <td class="text-right">₱{{ number_format((float) $po->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No purchase orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $orders->links() }}</div>
        </div>

        @if (auth()->user()->hasPermissionTo('purchasing.manage') && $suppliers->isNotEmpty() && $locations->isNotEmpty())
            <form method="POST" action="{{ route('inventory.purchase-orders.store') }}" class="card p-6 space-y-2" x-data="{ lines: [0] }">
                @csrf
                <h2 class="text-lg">New purchase order</h2>
                <select name="supplier_id" class="form-input" aria-label="Supplier">
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
                <select name="stock_location_id" class="form-input" aria-label="Deliver to">
                    @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                </select>
                <input name="expected_on" type="date" class="form-input" aria-label="Expected on" />
                <template x-for="(line, i) in lines" :key="i">
                    <div class="flex gap-1">
                        <select :name="`lines[${i}][inventory_item_id]`" class="form-input" aria-label="Item">
                            @foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>@endforeach
                        </select>
                        <input :name="`lines[${i}][quantity]`" type="number" step="0.001" min="0" class="form-input" placeholder="Qty" style="width:80px" aria-label="Quantity" />
                        <input :name="`lines[${i}][unit_cost]`" type="number" step="0.0001" min="0" class="form-input" placeholder="₱/unit" style="width:90px" aria-label="Unit cost" />
                    </div>
                </template>
                <button type="button" class="btn btn-sm btn-ghost" @click="lines.push(lines.length)">+ Line</button>
                <button type="submit" class="btn btn-primary w-full">Save draft</button>
            </form>
        @endif
    </div>
</x-app-layout>
