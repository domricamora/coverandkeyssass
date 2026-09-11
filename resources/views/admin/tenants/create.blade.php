<x-app-layout>
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-semibold text-brand-950 dark:text-sand-50">Create tenant</h1>
        <p class="mt-1 text-sm text-brand-600 dark:text-brand-300">Attach an existing user by email as the tenant owner.</p>

        <form method="POST" action="{{ route('admin.tenants.store') }}" class="hoso-card mt-6 space-y-5 p-6">
            @csrf
            <div>
                <label for="name" class="hoso-label">Business name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="hoso-input" />
                <x-input-error :messages="$errors->get('name')" class="hoso-error" />
            </div>
            <div>
                <label for="business_type" class="hoso-label">Business type</label>
                <select id="business_type" name="business_type" class="hoso-input">
                    @foreach (['hotel' => 'Hotel', 'resort' => 'Resort', 'bnb' => 'Bed & Breakfast', 'guesthouse' => 'Guesthouse', 'apartment' => 'Apartment', 'condo' => 'Condo', 'villa' => 'Villa', 'hostel' => 'Hostel', 'restaurant' => 'Restaurant'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('business_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('business_type')" class="hoso-error" />
            </div>
            <div>
                <label for="owner_email" class="hoso-label">Owner email (existing user)</label>
                <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required class="hoso-input" />
                <x-input-error :messages="$errors->get('owner_email')" class="hoso-error" />
            </div>
            <div class="flex justify-end gap-2 border-t border-sand-100 pt-4 dark:border-brand-800">
                <a href="{{ route('admin.tenants.index') }}" class="hoso-btn-secondary">Cancel</a>
                <button type="submit" class="hoso-btn-primary">Create tenant</button>
            </div>
        </form>
    </div>
</x-app-layout>
