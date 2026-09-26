<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Edit — {{ $property->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Profile, policies, amenities, location, photos and videos.</p>
        </div>
        <a href="{{ route('properties.show', $property) }}" class="btn btn-ghost">Back to property</a>
    </div>

    <div class="card mt-6 p-6" style="max-width:820px">
        <h2 class="text-lg">Profile</h2>
        <form method="POST" action="{{ route('properties.update', $property) }}" class="mt-3 space-y-4">
            @csrf
            @method('PATCH')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="name" class="form-label">Property name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $property->name) }}" required class="form-input" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <label for="tagline" class="form-label">Tagline</label>
                    <input id="tagline" name="tagline" type="text" value="{{ old('tagline', $property->tagline) }}" class="form-input" />
                    <x-input-error :messages="$errors->get('tagline')" />
                </div>
            </div>
            <div>
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="4" class="form-input">{{ old('description', $property->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="property_type_id" class="form-label">Property type</label>
                    <select id="property_type_id" name="property_type_id" class="form-input">
                        <option value="">—</option>
                        @foreach ($propertyTypes as $type)
                            <option value="{{ $type->getKey() }}" @selected(old('property_type_id', $property->property_type_id) == $type->getKey())>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="location_id" class="form-label">Destination</label>
                    <select id="location_id" name="location_id" class="form-input">
                        <option value="">—</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->getKey() }}" @selected(old('location_id', $property->location_id) == $location->getKey())>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="address_line" class="form-label">Address</label>
                    <input id="address_line" name="address_line" type="text" value="{{ old('address_line', $property->address_line) }}" class="form-input" />
                </div>
                <div>
                    <label for="city" class="form-label">City</label>
                    <input id="city" name="city" type="text" value="{{ old('city', $property->city) }}" class="form-input" />
                </div>
                <div>
                    <label for="region" class="form-label">Region</label>
                    <input id="region" name="region" type="text" value="{{ old('region', $property->region) }}" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="max_guests" class="form-label">Max guests</label>
                    <input id="max_guests" name="max_guests" type="number" min="1" value="{{ old('max_guests', $property->max_guests) }}" required class="form-input" />
                    <x-input-error :messages="$errors->get('max_guests')" />
                </div>
                <div>
                    <label for="bedrooms" class="form-label">Bedrooms</label>
                    <input id="bedrooms" name="bedrooms" type="number" min="0" value="{{ old('bedrooms', $property->bedrooms) }}" class="form-input" />
                </div>
                <div>
                    <label for="beds" class="form-label">Beds</label>
                    <input id="beds" name="beds" type="number" min="0" value="{{ old('beds', $property->beds) }}" class="form-input" />
                </div>
                <div>
                    <label for="bathrooms" class="form-label">Bathrooms</label>
                    <input id="bathrooms" name="bathrooms" type="number" min="0" value="{{ old('bathrooms', $property->bathrooms) }}" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="base_price" class="form-label">Nightly rate</label>
                    <input id="base_price" name="base_price" type="number" step="0.01" min="0" value="{{ old('base_price', $property->base_price) }}" required class="form-input" />
                    <x-input-error :messages="$errors->get('base_price')" />
                </div>
                <div>
                    <label for="weekend_price" class="form-label">Weekend rate</label>
                    <input id="weekend_price" name="weekend_price" type="number" step="0.01" min="0" value="{{ old('weekend_price', $property->weekend_price) }}" class="form-input" />
                </div>
                <div>
                    <label for="cleaning_fee" class="form-label">Cleaning fee</label>
                    <input id="cleaning_fee" name="cleaning_fee" type="number" step="0.01" min="0" value="{{ old('cleaning_fee', $property->cleaning_fee) }}" class="form-input" />
                </div>
                <div>
                    <label for="currency" class="form-label">Currency</label>
                    <input id="currency" name="currency" type="text" maxlength="3" value="{{ old('currency', $property->currency) }}" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="check_in_time" class="form-label">Check-in (HH:MM)</label>
                    <input id="check_in_time" name="check_in_time" type="time" value="{{ old('check_in_time', $property->check_in_time) }}" class="form-input" />
                </div>
                <div>
                    <label for="check_out_time" class="form-label">Check-out (HH:MM)</label>
                    <input id="check_out_time" name="check_out_time" type="time" value="{{ old('check_out_time', $property->check_out_time) }}" class="form-input" />
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">Save profile</button>
            </div>
        </form>
    </div>

    <div class="card mt-6 p-6" style="max-width:820px">
        <h2 class="text-lg">Policies</h2>
        <form method="POST" action="{{ route('properties.update', $property) }}" class="mt-3 space-y-3">
            @csrf
            @method('PATCH')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="policy_free_cancellation_days" class="form-label">Free cancellation (days before check-in)</label>
                    <input id="policy_free_cancellation_days" name="policy_free_cancellation_days" type="number" min="0" max="60" class="form-input" value="{{ old('policy_free_cancellation_days', $property->policies['free_cancellation_days'] ?? '') }}" placeholder="Empty = non-refundable">
                    <p class="mt-1 text-xs" style="color:var(--text-3)">Shows a “Free cancellation” badge and lets guests filter for it. 0 = free until the day of arrival.</p>
                    <x-input-error :messages="$errors->get('policy_free_cancellation_days')" />
                </div>
                <div>
                    <label for="policy_cancellation" class="form-label">Cancellation</label>
                    <textarea id="policy_cancellation" name="policy_cancellation" rows="2" class="form-input">{{ old('policy_cancellation', $property->policies['cancellation'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="policy_children" class="form-label">Children</label>
                    <textarea id="policy_children" name="policy_children" rows="2" class="form-input">{{ old('policy_children', $property->policies['children'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="policy_pets" class="form-label">Pets</label>
                    <textarea id="policy_pets" name="policy_pets" rows="2" class="form-input">{{ old('policy_pets', $property->policies['pets'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="policy_noise" class="form-label">Noise / quiet hours</label>
                    <textarea id="policy_noise" name="policy_noise" rows="2" class="form-input">{{ old('policy_noise', $property->policies['noise'] ?? '') }}</textarea>
                </div>
                <div>
                    <label for="policy_smoking" class="form-label">Smoking</label>
                    <textarea id="policy_smoking" name="policy_smoking" rows="2" class="form-input">{{ old('policy_smoking', $property->policies['smoking'] ?? '') }}</textarea>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">Save policies</button>
            </div>
        </form>
    </div>

    <div class="card mt-6 p-6" style="max-width:820px">
        <h2 class="text-lg">Amenities</h2>
        <form method="POST" action="{{ route('properties.update', $property) }}" class="mt-3">
            @csrf
            @method('PATCH')
            <div class="grid grid-cols-3 gap-2">
                @foreach ($amenities as $amenity)
                    <label class="flex items-center gap-2 text-sm" style="color:var(--text-2)">
                        <input type="checkbox" name="amenities[]" value="{{ $amenity->getKey() }}" @checked(in_array($amenity->getKey(), old('amenities', $property->amenities->modelKeys()))) />
                        {{ $amenity->name }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('amenities.*')" />
            <div class="flex justify-end mt-3">
                <button type="submit" class="btn btn-primary">Save amenities</button>
            </div>
        </form>
    </div>

    <div class="card mt-6 p-6" style="max-width:820px">
        <h2 class="text-lg">Photos</h2>
        @forelse ($photos as $photo)
            <div class="flex items-center gap-3 mt-3">
                <img src="{{ $photo->url() }}" alt="{{ $photo->alt ?? 'Photo' }}" style="width:96px;height:64px;object-fit:cover" class="rounded" />
                <div class="flex-1 text-sm" style="color:var(--text-2)">
                    <span class="badge badge-gray">{{ $photo->caption ?: 'Gallery' }}</span>
                    {{ $photo->alt ?? basename($photo->path) }}
                    @if ($photo->is_cover) <span class="badge badge-green">Cover</span> @endif
                </div>
                @unless ($photo->is_cover)
                    <form method="POST" action="{{ route('properties.media.cover', [$property, $photo]) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost">Make cover</button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('properties.media.destroy', [$property, $photo]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                </form>
            </div>
        @empty
            <p class="mt-2 text-sm" style="color:var(--text-3)">No photos yet.</p>
        @endforelse
        @include('partials.media-upload', ['action' => route('properties.media.store', $property), 'areas' => \App\Support\MediaUploads::AREAS['property'], 'listId' => 'property-media'])
    </div>

    <div class="card mt-6 p-6 mb-10" style="max-width:820px">
        <h2 class="text-lg">Videos</h2>
        @forelse ($videos as $video)
            <div class="flex items-center gap-3 mt-3">
                <span class="badge badge-blue">Video</span>
                <span class="flex-1 text-sm" style="color:var(--text-2)">{{ $video->alt ?? $video->path }}</span>
                <form method="POST" action="{{ route('properties.media.destroy', [$property, $video]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                </form>
            </div>
        @empty
            <p class="mt-2 text-sm" style="color:var(--text-3)">No videos yet.</p>
        @endforelse
        <form method="POST" action="{{ route('properties.media.store', $property) }}" class="mt-4 flex gap-2 items-end">
            @csrf
            <div class="flex-1">
                <label for="video_url" class="form-label">Add video (URL)</label>
                <input id="video_url" name="url" type="url" required class="form-input" placeholder="https://…/tour.mp4" />
            </div>
            <input type="hidden" name="kind" value="video" />
            <button type="submit" class="btn btn-primary">Add video</button>
        </form>
    </div>
</x-app-layout>
