@php($canManage = auth()->user()->hasPermissionTo('inventory.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $item->name }} <small style="color:var(--text-3)">{{ $item->sku }}</small></h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $item->qty($item->totalStock()) }} on hand · avg ₱{{ number_format((float) $item->cost_per_unit, 2) }}/{{ $item->unit }} · reorder at {{ $item->qty($item->reorder_level) }}</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn-ghost">All items</a>
    </div>
    @include('inventory::partials.nav')

    @foreach (['quantity', 'location_id', 'to_location_id', 'notes', 'name', 'reorder_level'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="space-y-4" style="grid-column:span 2">
            <div class="card p-6">
                <h2 class="text-lg">By location</h2>
                <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                    @forelse ($item->levels as $level)
                        <li>{{ $level->location?->name }} · <strong>{{ $item->qty($level->quantity) }}</strong> @if ((float) $level->quantity < 0)<span class="badge badge-red">negative — count it</span>@endif</li>
                    @empty
                        <li style="color:var(--text-3)">No stock anywhere yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="table-wrap card">
                <table class="table">
                    <thead><tr><th>When</th><th>Movement</th><th>Location</th><th class="text-right">Qty</th><th class="text-right">Balance</th><th>By / ref</th></tr></thead>
                    <tbody>
                        @forelse ($movements as $m)
                            <tr>
                                <td>{{ $m->created_at->format('M j, g:i A') }}</td>
                                <td>{{ $m->typeLabel() }}@if ($m->notes)<br><small style="color:var(--text-3)">{{ $m->notes }}</small>@endif</td>
                                <td>{{ $m->location?->name }}</td>
                                <td class="text-right" style="color:{{ (float) $m->quantity < 0 ? 'var(--danger, #b91c1c)' : 'inherit' }}">{{ (float) $m->quantity > 0 ? '+' : '' }}{{ $item->qty($m->quantity) }}</td>
                                <td class="text-right">{{ $item->qty($m->balance_after) }}</td>
                                <td style="color:var(--text-3)">{{ $m->user?->name ?? 'system' }}{{ $m->reference ? ' · '.$m->reference : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-sm" style="color:var(--text-3)">No movements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($canManage)
            <div class="space-y-4">
                <form method="POST" action="{{ route('inventory.items.move', $item->id) }}" class="card p-6 space-y-2" x-data="{ action: 'receive' }">
                    @csrf
                    <h2 class="text-lg">Stock movement</h2>
                    <select name="action" class="form-input" x-model="action" aria-label="Movement">
                        <option value="receive">Receive (delivery)</option>
                        <option value="issue">Issue (to a department)</option>
                        <option value="waste">Waste / spoilage</option>
                        <option value="count">Stock count (set to)</option>
                        <option value="transfer">Transfer</option>
                    </select>
                    <select name="location_id" class="form-input" aria-label="Location">
                        @foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                    </select>
                    <select name="to_location_id" class="form-input" x-show="action === 'transfer'" aria-label="Transfer to">
                        @foreach ($locations as $location)<option value="{{ $location->id }}">→ {{ $location->name }}</option>@endforeach
                    </select>
                    <input name="quantity" type="number" step="0.001" min="0" required class="form-input" placeholder="Quantity ({{ $item->unit }})" aria-label="Quantity" />
                    <input name="unit_cost" type="number" step="0.0001" min="0" class="form-input" x-show="action === 'receive'" placeholder="Unit cost ₱" aria-label="Unit cost" />
                    <input name="notes" type="text" class="form-input" placeholder="Notes / reason" aria-label="Notes" />
                    <button type="submit" class="btn btn-primary w-full">Save movement</button>
                </form>

                <form method="POST" action="{{ route('inventory.items.update', $item->id) }}" class="card p-6 space-y-2">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-lg">Item</h2>
                    <input name="name" type="text" value="{{ $item->name }}" required class="form-input" aria-label="Name" />
                    <select name="inventory_category_id" class="form-input" aria-label="Category">
                        <option value="">No category</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($item->inventory_category_id === $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                    <input name="reorder_level" type="number" step="0.001" min="0" value="{{ (float) $item->reorder_level }}" required class="form-input" aria-label="Reorder level" />
                    <label class="text-sm flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($item->is_active) /> Active</label>
                    <button type="submit" class="btn btn-ghost w-full">Save</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
