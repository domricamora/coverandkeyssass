{{-- Item picker: menu item + its modifier options (shown for the chosen item), qty, notes. $menu --}}
<div x-data="{ item: '{{ $menu->first()?->id }}' }" class="space-y-2">
    <select name="item_id" x-model="item" class="form-input" aria-label="Menu item">
        @foreach ($menu as $menuItem)
            <option value="{{ $menuItem->id }}">{{ $menuItem->name }} · {{ $menuItem->priceLabel() }}</option>
        @endforeach
    </select>
    @foreach ($menu as $menuItem)
        @foreach ($menuItem->modifierGroups->filter(fn ($g) => $g->options->isNotEmpty()) as $group)
            <fieldset x-show="item == '{{ $menuItem->id }}'" class="text-sm" style="border:0;padding:0;margin:0;">
                <legend><strong>{{ $group->name }}</strong> <small style="color:var(--text-3)">{{ $group->ruleLabel() }}</small></legend>
                @foreach ($group->options as $option)
                    <label class="flex items-center gap-1">
                        <input type="{{ $group->max_select === 1 ? 'radio' : 'checkbox' }}" name="options[]" value="{{ $option->id }}" :disabled="item != '{{ $menuItem->id }}'" />
                        {{ $option->name }}@if ((float) $option->price > 0) +₱{{ number_format((float) $option->price, 2) }}@endif
                    </label>
                @endforeach
            </fieldset>
        @endforeach
    @endforeach
    <div class="flex gap-2">
        <input name="quantity" type="number" min="1" max="50" value="1" class="form-input" style="width:80px" aria-label="Quantity" />
        <input name="notes" type="text" maxlength="255" class="form-input" placeholder="Notes (e.g. no onions)" aria-label="Notes" />
    </div>
</div>
