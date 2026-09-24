<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>New property</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Created as a draft — publish it when the profile is ready.</p>
        </div>
    </div>

    <div class="card mt-6 p-6" style="max-width:720px">
        <form method="POST" action="{{ route('properties.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="form-label">Property name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="form-input" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="tagline" class="form-label">Tagline</label>
                <input id="tagline" name="tagline" type="text" value="{{ old('tagline') }}" class="form-input" placeholder="Beachfront suites with sunset views" />
                <x-input-error :messages="$errors->get('tagline')" />
            </div>
            <div>
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="4" class="form-input">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="property_type_id" class="form-label">Property type</label>
                    <select id="property_type_id" name="property_type_id" class="form-input">
                        <option value="">—</option>
                        @foreach ($propertyTypes as $type)
                            <option value="{{ $type->getKey() }}" @selected(old('property_type_id') == $type->getKey())>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="location_id" class="form-label">Destination</label>
                    <select id="location_id" name="location_id" class="form-input">
                        <option value="">—</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->getKey() }}" @selected(old('location_id') == $location->getKey())>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="address_line" class="form-label">Address</label>
                    <input id="address_line" name="address_line" type="text" value="{{ old('address_line') }}" class="form-input" />
                </div>
                <div>
                    <label for="city" class="form-label">City</label>
                    <input id="city" name="city" type="text" value="{{ old('city') }}" class="form-input" />
                </div>
                <div>
                    <label for="region" class="form-label">Region</label>
                    <input id="region" name="region" type="text" value="{{ old('region') }}" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="max_guests" class="form-label">Max guests</label>
                    <input id="max_guests" name="max_guests" type="number" min="1" value="{{ old('max_guests', 2) }}" required class="form-input" />
                    <x-input-error :messages="$errors->get('max_guests')" />
                </div>
                <div>
                    <label for="bedrooms" class="form-label">Bedrooms</label>
                    <input id="bedrooms" name="bedrooms" type="number" min="0" value="{{ old('bedrooms', 1) }}" class="form-input" />
                </div>
                <div>
                    <label for="beds" class="form-label">Beds</label>
                    <input id="beds" name="beds" type="number" min="0" value="{{ old('beds', 1) }}" class="form-input" />
                </div>
                <div>
                    <label for="bathrooms" class="form-label">Bathrooms</label>
                    <input id="bathrooms" name="bathrooms" type="number" min="0" value="{{ old('bathrooms', 1) }}" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="base_price" class="form-label">Nightly rate</label>
                    <input id="base_price" name="base_price" type="number" step="0.01" min="0" value="{{ old('base_price') }}" required class="form-input" />
                    <x-input-error :messages="$errors->get('base_price')" />
                </div>
                <div>
                    <label for="weekend_price" class="form-label">Weekend rate</label>
                    <input id="weekend_price" name="weekend_price" type="number" step="0.01" min="0" value="{{ old('weekend_price') }}" class="form-input" />
                </div>
                <div>
                    <label for="cleaning_fee" class="form-label">Cleaning fee</label>
                    <input id="cleaning_fee" name="cleaning_fee" type="number" step="0.01" min="0" value="{{ old('cleaning_fee', 0) }}" class="form-input" />
                </div>
                <div>
                    <label for="currency" class="form-label">Currency</label>
                    <input id="currency" name="currency" type="text" maxlength="3" value="{{ old('currency', 'PHP') }}" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="check_in_time" class="form-label">Check-in (HH:MM)</label>
                    <input id="check_in_time" name="check_in_time" type="time" value="{{ old('check_in_time', '14:00') }}" class="form-input" />
                </div>
                <div>
                    <label for="check_out_time" class="form-label">Check-out (HH:MM)</label>
                    <input id="check_out_time" name="check_out_time" type="time" value="{{ old('check_out_time', '11:00') }}" class="form-input" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t pt-4" style="border-color:var(--border)">
                <a href="{{ route('properties.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Create property</button>
            </div>
        </form>
    </div>
</x-app-layout>
