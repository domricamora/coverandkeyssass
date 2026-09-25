@php($editing = $restaurant->exists)
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $editing ? 'Edit — '.$restaurant->name : 'New restaurant' }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Profile, opening hours and cuisines.</p>
        </div>
        <a href="{{ $editing ? route('restaurants.show', $restaurant) : route('restaurants.index') }}" class="btn btn-ghost">Cancel</a>
    </div>

    <form method="POST" action="{{ $editing ? route('restaurants.update', $restaurant) : route('restaurants.store') }}" class="card mt-6 p-6 space-y-4" style="max-width:820px">
        @csrf
        @if ($editing) @method('PATCH') @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="name" class="form-label">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $restaurant->name) }}" required class="form-input" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="tagline" class="form-label">Tagline</label>
                <input id="tagline" name="tagline" type="text" value="{{ old('tagline', $restaurant->tagline) }}" class="form-input" />
            </div>
        </div>
        <div>
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="4" class="form-input">{{ old('description', $restaurant->description) }}</textarea>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="location_id" class="form-label">Destination</label>
                <select id="location_id" name="location_id" class="form-input">
                    <option value="">—</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $restaurant->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="city" class="form-label">City</label>
                <input id="city" name="city" type="text" value="{{ old('city', $restaurant->city) }}" class="form-input" />
            </div>
            <div>
                <label for="region" class="form-label">Region</label>
                <input id="region" name="region" type="text" value="{{ old('region', $restaurant->region) }}" class="form-input" />
            </div>
        </div>
        <div>
            <label for="address_line" class="form-label">Address</label>
            <input id="address_line" name="address_line" type="text" value="{{ old('address_line', $restaurant->address_line) }}" class="form-input" />
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="phone" class="form-label">Phone</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $restaurant->phone) }}" class="form-input" />
            </div>
            <div>
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $restaurant->email) }}" class="form-input" />
                <x-input-error :messages="$errors->get('email')" />
            </div>
            <div>
                <label for="price_level" class="form-label">Price level</label>
                <select id="price_level" name="price_level" class="form-input">
                    @foreach ([1, 2, 3, 4] as $level)
                        <option value="{{ $level }}" @selected((int) old('price_level', $restaurant->price_level ?? 2) === $level)>{{ str_repeat('₱', $level) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex gap-6 text-sm" style="color:var(--text-2)">
            <label class="flex items-center gap-2"><input type="checkbox" name="reservations_enabled" value="1" @checked(old('reservations_enabled', $restaurant->reservations_enabled)) /> Accepts reservations</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="ordering_enabled" value="1" @checked(old('ordering_enabled', $restaurant->ordering_enabled)) /> Online ordering</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="delivery_enabled" value="1" @checked(old('delivery_enabled', $restaurant->delivery_enabled)) /> Offers delivery</label>
            <label class="flex items-center gap-2">Sitting length
                <input name="reservation_duration_minutes" type="number" min="15" max="480" step="15" value="{{ old('reservation_duration_minutes', $restaurant->reservation_duration_minutes ?? 90) }}" class="form-input" style="width:90px" /> min
            </label>
        </div>

        <div class="flex gap-6 items-center text-sm" style="color:var(--text-2)">
            <label class="flex items-center gap-2">Tax rate
                <input name="tax_rate" type="number" min="0" max="50" step="0.01" value="{{ old('tax_rate', $restaurant->tax_rate ?? 12) }}" class="form-input" style="width:90px" /> %
            </label>
            <label class="flex items-center gap-2"><input type="checkbox" name="tax_inclusive" value="1" @checked(old('tax_inclusive', $restaurant->exists ? $restaurant->tax_inclusive : true)) /> Menu prices include tax (VAT)</label>
        </div>

        <h2 class="text-lg pt-2">Opening hours</h2>
        <p class="text-sm" style="color:var(--text-3)">e.g. "11:00–22:00" or "Closed". Leave blank to hide a day.</p>
        <div class="grid grid-cols-2 gap-3">
            @foreach ($days as $day)
                <div class="flex items-center gap-2">
                    <label for="hours_{{ $day }}" class="form-label" style="width:110px;margin:0">{{ ucfirst($day) }}</label>
                    <input id="hours_{{ $day }}" name="hours[{{ $day }}]" type="text" value="{{ old("hours.$day", $restaurant->opening_hours[$day] ?? '') }}" class="form-input" />
                </div>
            @endforeach
        </div>

        <h2 class="text-lg pt-2">Cuisines</h2>
        <div class="grid grid-cols-3 gap-2">
            @foreach ($cuisines as $cuisine)
                <label class="flex items-center gap-2 text-sm" style="color:var(--text-2)">
                    <input type="checkbox" name="cuisines[]" value="{{ $cuisine->id }}" @checked(in_array($cuisine->id, old('cuisines', $editing ? $restaurant->cuisines->modelKeys() : []))) />
                    {{ $cuisine->name }}
                </label>
            @endforeach
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Save profile' : 'Create restaurant' }}</button>
        </div>
    </form>

    @if ($editing)
        <div class="card mt-6 p-6 mb-10" style="max-width:820px">
            <h2 class="text-lg">Photos</h2>
            @forelse ($photos as $photo)
                <div class="flex items-center gap-3 mt-3">
                    <img src="{{ $photo->url() }}" alt="{{ $photo->alt ?? 'Photo' }}" style="width:96px;height:64px;object-fit:cover" class="rounded" />
                    <div class="flex-1 text-sm" style="color:var(--text-2)">
                        {{ $photo->alt ?? $photo->path }}
                        @if ($photo->is_cover) <span class="badge badge-green">Cover</span> @endif
                    </div>
                    @unless ($photo->is_cover)
                        <form method="POST" action="{{ route('restaurants.media.cover', [$restaurant, $photo]) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-ghost">Make cover</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('restaurants.media.destroy', [$restaurant, $photo]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                    </form>
                </div>
            @empty
                <p class="mt-2 text-sm" style="color:var(--text-3)">No photos yet.</p>
            @endforelse
            <form method="POST" action="{{ route('restaurants.media.store', $restaurant) }}" class="mt-4 flex gap-2 items-end">
                @csrf
                <div class="flex-1">
                    <label for="photo_url" class="form-label">Add photo (URL)</label>
                    <input id="photo_url" name="url" type="url" required class="form-input" placeholder="https://…" />
                    <x-input-error :messages="$errors->get('url')" />
                </div>
                <button type="submit" class="btn btn-primary">Add photo</button>
            </form>
        </div>
    @endif
</x-app-layout>
