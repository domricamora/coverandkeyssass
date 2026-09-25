@php
    $canManage = auth()->user()->hasPermissionTo('inventory.manage');
    $canBuy = auth()->user()->hasPermissionTo('purchasing.manage');
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Inventory</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $items->count() }} item(s) ·
                <a href="{{ route('inventory.index', ['low' => 1]) }}" class="{{ $lowCount ? 'badge badge-red' : '' }}">{{ $lowCount }} low on stock</a>
            </p>
        </div>
    </div>
    @include('inventory::partials.nav')

    @foreach (['sku', 'name', 'unit', 'inventory_category_id'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Item</th><th>Category</th><th class="text-right">On hand</th><th class="text-right">Reorder at</th><th class="text-right">Avg cost</th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        @php($total = $item->totalStock())
                        @php($low = (float) $item->reorder_level > 0 && $total <= (float) $item->reorder_level)
                        <tr style="{{ $item->is_active ? '' : 'opacity:.55' }}">
                            <td><a href="{{ route('inventory.items.show', $item->id) }}"><strong>{{ $item->name }}</strong></a><br><small style="color:var(--text-3)">{{ $item->sku }}</small></td>
                            <td style="color:var(--text-2)">{{ $item->category?->name ?? '—' }}</td>
                            <td class="text-right">@if ($low)<span class="badge badge-red">{{ $item->qty($total) }}</span>@else {{ $item->qty($total) }} @endif</td>
                            <td class="text-right" style="color:var(--text-2)">{{ (float) $item->reorder_level > 0 ? $item->qty($item->reorder_level) : '—' }}</td>
                            <td class="text-right" style="color:var(--text-2)">₱{{ number_format((float) $item->cost_per_unit, 2) }}/{{ $item->unit }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            @if ($canManage)
                <form method="POST" action="{{ route('inventory.items.store') }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">New item</h2>
                    <div class="flex gap-2">
                        <input name="sku" type="text" required class="form-input" placeholder="SKU" style="width:110px" aria-label="SKU" />
                        <input name="name" type="text" required class="form-input" placeholder="Burger bun" aria-label="Name" />
                    </div>
                    <div class="flex gap-2">
                        <select name="unit" class="form-input" aria-label="Unit">
                            @foreach ($units as $unit)<option value="{{ $unit }}">{{ $unit }}</option>@endforeach
                        </select>
                        <select name="inventory_category_id" class="form-input" aria-label="Category">
                            <option value="">No category</option>
                            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <input name="reorder_level" type="number" step="0.001" min="0" class="form-input" placeholder="Reorder level" aria-label="Reorder level" />
                        <input name="cost_per_unit" type="number" step="0.0001" min="0" class="form-input" placeholder="Cost / unit" aria-label="Cost per unit" />
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Add item</button>
                </form>
            @endif

            @foreach ([['Categories', $categories, 'inventory.categories.store', 'Meat', $canManage], ['Locations', $locations, 'inventory.locations.store', 'Main store', $canManage], ['Suppliers', $suppliers, 'inventory.suppliers.store', 'Island Meats Co.', $canBuy]] as [$heading, $rows, $route, $placeholder, $allowed])
                <div class="card p-6">
                    <h2 class="text-lg">{{ $heading }}</h2>
                    <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                        @forelse ($rows as $row)
                            <li>{{ $row->name }}</li>
                        @empty
                            <li style="color:var(--text-3)">None yet.</li>
                        @endforelse
                    </ul>
                    @if ($allowed)
                        <form method="POST" action="{{ route($route) }}" class="flex gap-2 mt-3">
                            @csrf
                            <input name="name" type="text" required class="form-input" placeholder="{{ $placeholder }}" aria-label="{{ $heading }} name" />
                            <button type="submit" class="btn btn-sm btn-ghost">Add</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
