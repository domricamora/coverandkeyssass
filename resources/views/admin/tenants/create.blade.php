<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Create tenant</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Attach an existing user by email as the tenant owner.</p>
        </div>
    </div>

    <div class="card mt-6 p-6" style="max-width:560px">
        <form method="POST" action="{{ route('admin.tenants.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="form-label">Business name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="form-input" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="business_type" class="form-label">Business type</label>
                <select id="business_type" name="business_type" class="form-input">
                    @foreach (['hotel' => 'Hotel', 'resort' => 'Resort', 'bnb' => 'Bed & Breakfast', 'guesthouse' => 'Guesthouse', 'apartment' => 'Apartment', 'condo' => 'Condo', 'villa' => 'Villa', 'hostel' => 'Hostel', 'restaurant' => 'Restaurant'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('business_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('business_type')" />
            </div>
            <div>
                <label for="owner_email" class="form-label">Owner email (existing user)</label>
                <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required class="form-input" />
                <x-input-error :messages="$errors->get('owner_email')" />
            </div>
            <div class="flex justify-end gap-2 border-t pt-4" style="border-color:var(--border)">
                <a href="{{ route('admin.tenants.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Create tenant</button>
            </div>
        </form>
    </div>
</x-app-layout>