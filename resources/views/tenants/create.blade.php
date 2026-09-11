<x-app-layout>
    <div class="auth-wrap" style="min-height:auto;padding:0">
        <div class="auth-card card" style="width:min(560px,100%)">
            <h1>Create a business</h1>
            <p class="auth-card__sub">You become the owner with full control of this account.</p>

            <form method="POST" action="{{ route('tenants.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="name" class="form-label">Business name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="form-input" placeholder="e.g. Casa Marina Resort" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1 text-xs" style="color:var(--red)" />
                </div>

                <div>
                    <label for="business_type" class="form-label">Business type</label>
                    <select id="business_type" name="business_type" class="form-input">
                        @foreach ([
                            'hotel' => 'Hotel',
                            'resort' => 'Resort',
                            'bnb' => 'Bed & Breakfast',
                            'guesthouse' => 'Guesthouse',
                            'apartment' => 'Apartment',
                            'condo' => 'Condo',
                            'villa' => 'Villa',
                            'hostel' => 'Hostel',
                            'restaurant' => 'Restaurant',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('business_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('business_type')" class="mt-1 text-xs" style="color:var(--red)" />
                </div>

                <div class="flex justify-end gap-2 border-t pt-4" style="border-color:var(--border)">
                    <a href="{{ route('tenants.index') }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create business</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>