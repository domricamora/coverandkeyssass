@php($canManage = auth()->user()->hasPermissionTo('inventory.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Recipes</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">What each dish uses. Stock is deducted when the kitchen accepts an order.</p>
        </div>
        <form method="GET">
            <select name="restaurant" class="form-input" onchange="this.form.submit()" aria-label="Restaurant">
                @foreach ($restaurants as $r)<option value="{{ $r->slug }}" @selected($restaurant?->id === $r->id)>{{ $r->name }}</option>@endforeach
            </select>
        </form>
    </div>
    @include('inventory::partials.nav')

    @foreach (['unit', 'quantity', 'menu_item_id', 'inventory_item_id'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    @if (! $restaurant)
        <div class="card p-6 text-sm" style="color:var(--text-3)">Add a restaurant first.</div>
    @else
        <form method="POST" action="{{ route('inventory.recipes.location', $restaurant->slug) }}" class="card p-4 mb-4 flex gap-2 items-center">
            @csrf
            <span class="text-sm">Kitchen stock location for {{ $restaurant->name }}:</span>
            <select name="stock_location_id" class="form-input" style="width:auto" aria-label="Kitchen stock location" @disabled(! $canManage)>
                <option value="">Not linked (sales do not touch stock)</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected($restaurant->stock_location_id === $l->id)>{{ $l->name }}</option>@endforeach
            </select>
            @if ($canManage)<button type="submit" class="btn btn-sm btn-ghost">Save</button>@endif
        </form>

        <div class="grid grid-cols-2 gap-4">
            @forelse ($menuItems as $menuItem)
                <div class="card p-4 text-sm">
                    <strong style="color:var(--text)">{{ $menuItem->name }}</strong> <small style="color:var(--text-3)">{{ $menuItem->priceLabel() }}</small>
                    <ul class="mt-2 space-y-1" style="color:var(--text-2)">
                        @forelse ($ingredients->get($menuItem->id, collect()) as $ing)
                            <li class="flex justify-between items-center">
                                <span>{{ $ing->entered_quantity ? rtrim(rtrim(number_format((float) $ing->entered_quantity, 3), '0'), '.').' '.$ing->entered_unit : $ing->item->qty($ing->quantity) }} {{ $ing->item?->name }}
                                    @if ($ing->entered_unit && $ing->entered_unit !== $ing->item?->unit)<small style="color:var(--text-3)">(= {{ $ing->item->qty($ing->quantity) }})</small>@endif</span>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('inventory.recipes.destroy', [$restaurant->slug, $ing->id]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove {{ $ing->item?->name }}">×</button></form>
                                @endif
                            </li>
                        @empty
                            <li style="color:var(--text-3)">No recipe.</li>
                        @endforelse
                    </ul>
                    @if ($canManage && $items->isNotEmpty())
                        <form method="POST" action="{{ route('inventory.recipes.store', $restaurant->slug) }}" class="flex gap-1 mt-2">
                            @csrf
                            <input type="hidden" name="menu_item_id" value="{{ $menuItem->id }}" />
                            <input name="quantity" type="number" step="0.001" min="0.001" required class="form-input" placeholder="Qty" style="width:70px" aria-label="Quantity" />
                            <select name="unit" class="form-input" style="width:80px" aria-label="Unit">
                                @foreach ($units as $u)<option value="{{ $u }}">{{ $u }}</option>@endforeach
                            </select>
                            <select name="inventory_item_id" class="form-input" aria-label="Stock item">
                                @foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>@endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Add</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="card p-6 text-sm" style="color:var(--text-3)">This restaurant has no menu items.</div>
            @endforelse
        </div>
    @endif
</x-app-layout>
