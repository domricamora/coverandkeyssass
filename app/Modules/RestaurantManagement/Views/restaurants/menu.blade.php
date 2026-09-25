@php($canManage = auth()->user()->hasPermissionTo('menu.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Menu — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Categories, items, prices, modifiers and add-ons.</p>
        </div>
        <a href="{{ route('restaurants.show', $restaurant) }}" class="btn btn-ghost">Back to restaurant</a>
    </div>

    <x-input-error :messages="$errors->get('category')" />
    <x-input-error :messages="$errors->get('menu_category_id')" />
    <x-input-error :messages="$errors->get('name')" />
    <x-input-error :messages="$errors->get('price')" />
    <x-input-error :messages="$errors->get('max_select')" />

    @if ($canManage)
        <div class="grid grid-cols-2 gap-4 mt-6" style="max-width:1100px">
            <form method="POST" action="{{ route('restaurants.categories.store', $restaurant) }}" class="card p-6 space-y-3">
                @csrf
                <h2 class="text-lg">Add category</h2>
                <input name="name" type="text" required class="form-input" placeholder="Burgers" aria-label="Category name" />
                <input name="description" type="text" class="form-input" placeholder="Description (optional)" aria-label="Category description" />
                <div class="flex justify-end"><button type="submit" class="btn btn-primary">Add category</button></div>
            </form>

            @if ($categories->isNotEmpty())
                <form method="POST" action="{{ route('restaurants.items.store', $restaurant) }}" class="card p-6 space-y-3">
                    @csrf
                    <h2 class="text-lg">Add menu item</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <select name="menu_category_id" class="form-input" aria-label="Category">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <input name="price" type="number" step="0.01" min="0" required class="form-input" placeholder="Price (₱)" aria-label="Price" />
                    </div>
                    <input name="name" type="text" required class="form-input" placeholder="Burger" aria-label="Item name" />
                    <input name="description" type="text" class="form-input" placeholder="Description (optional)" aria-label="Item description" />
                    <input name="photo_url" type="url" class="form-input" placeholder="Photo URL (optional)" aria-label="Photo URL" />
                    <div class="flex justify-end"><button type="submit" class="btn btn-primary">Add item</button></div>
                </form>
            @endif
        </div>
    @endif

    @forelse ($categories as $category)
        <div class="card mt-6 p-6" style="max-width:1100px">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-lg">{{ $category->name }} @unless ($category->is_active)<span class="badge badge-gray">Hidden</span>@endunless</h2>
                    @if ($category->description)<p class="text-sm mt-1" style="color:var(--text-3)">{{ $category->description }}</p>@endif
                </div>
                @if ($canManage)
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('restaurants.categories.update', [$restaurant, $category->id]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="name" value="{{ $category->name }}" />
                            <input type="hidden" name="description" value="{{ $category->description }}" />
                            @unless ($category->is_active)<input type="hidden" name="is_active" value="1" />@endunless
                            <button type="submit" class="btn btn-sm btn-ghost">{{ $category->is_active ? 'Hide' : 'Show' }}</button>
                        </form>
                        <form method="POST" action="{{ route('restaurants.categories.destroy', [$restaurant, $category->id]) }}" onsubmit="return confirm('Remove this category?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </div>
                @endif
            </div>

            @forelse ($category->items as $item)
                <div class="mt-4 pt-4" style="border-top:1px solid var(--border)">
                    <div class="flex justify-between items-start gap-4">
                        <div class="flex gap-3">
                            @if ($item->photo_url)
                                <img src="{{ $item->photo_url }}" alt="{{ $item->name }}" style="width:64px;height:64px;object-fit:cover" class="rounded" />
                            @endif
                            <div>
                                <strong style="color:var(--text)">{{ $item->name }}</strong>
                                <span style="color:var(--text-2)">· {{ $item->priceLabel() }}</span>
                                @unless ($item->is_available)<span class="badge badge-amber">Unavailable</span>@endunless
                                @if ($item->description)<p class="text-sm" style="color:var(--text-3)">{{ $item->description }}</p>@endif
                            </div>
                        </div>
                        @if ($canManage)
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('restaurants.items.update', [$restaurant, $item->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    @foreach (['menu_category_id', 'name', 'description', 'price', 'photo_url', 'sort_order'] as $field)
                                        <input type="hidden" name="{{ $field }}" value="{{ $item->{$field} }}" />
                                    @endforeach
                                    @unless ($item->is_available)<input type="hidden" name="is_available" value="1" />@endunless
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ $item->is_available ? 'Mark unavailable' : 'Mark available' }}</button>
                                </form>
                                <form method="POST" action="{{ route('restaurants.items.destroy', [$restaurant, $item->id]) }}" onsubmit="return confirm('Remove this item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                </form>
                            </div>
                        @endif
                    </div>

                    @foreach ($item->modifierGroups as $group)
                        <div class="mt-2 ml-4 text-sm" style="color:var(--text-2)">
                            <div class="flex items-center gap-2">
                                <strong>{{ $group->name }}</strong> <small style="color:var(--text-3)">{{ $group->ruleLabel() }}</small>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('restaurants.groups.destroy', [$restaurant, $item->id, $group->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost" aria-label="Remove group {{ $group->name }}">×</button>
                                    </form>
                                @endif
                            </div>
                            <ul class="ml-4">
                                @foreach ($group->options as $option)
                                    <li class="flex items-center gap-2">
                                        {{ $option->name }} +{{ \App\Modules\RestaurantManagement\Models\MenuItem::money((float) $option->price, $item->currency) }}
                                        @if ($canManage)
                                            <form method="POST" action="{{ route('restaurants.options.destroy', [$restaurant, $item->id, $group->id, $option->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-ghost" aria-label="Remove option {{ $option->name }}">×</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if ($canManage)
                                <form method="POST" action="{{ route('restaurants.options.store', [$restaurant, $item->id, $group->id]) }}" class="flex gap-2 ml-4 mt-1">
                                    @csrf
                                    <input name="name" type="text" required class="form-input" placeholder="Cheese" aria-label="Option name" />
                                    <input name="price" type="number" step="0.01" min="0" class="form-input" placeholder="+₱" style="width:110px" aria-label="Option price" />
                                    <button type="submit" class="btn btn-sm btn-ghost">Add option</button>
                                </form>
                            @endif
                        </div>
                    @endforeach

                    @if ($canManage)
                        <details class="mt-2 ml-4">
                            <summary class="text-sm cursor-pointer" style="color:var(--text-3)">+ Modifier group / add-ons</summary>
                            <form method="POST" action="{{ route('restaurants.groups.store', [$restaurant, $item->id]) }}" class="flex gap-2 mt-2 items-end">
                                @csrf
                                <input name="name" type="text" required class="form-input" placeholder="Add-ons" aria-label="Group name" />
                                <input name="min_select" type="number" min="0" value="0" class="form-input" style="width:90px" aria-label="Minimum choices" title="Minimum choices" />
                                <input name="max_select" type="number" min="1" class="form-input" style="width:90px" placeholder="Max" aria-label="Maximum choices" title="Maximum choices (blank = no limit)" />
                                <button type="submit" class="btn btn-sm btn-primary">Add group</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="mt-3 text-sm" style="color:var(--text-3)">No items in this category yet.</p>
            @endforelse
        </div>
    @empty
        <div class="card mt-6 p-6 text-sm" style="max-width:1100px;color:var(--text-3)">No menu yet — start with a category.</div>
    @endforelse
</x-app-layout>
