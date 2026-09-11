<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Business settings</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $tenant->name }} &middot; {{ $tenant->slug }}</p>
        </div>
    </div>

    <div class="card mt-6 p-6" style="max-width:560px">
        <form method="POST" action="{{ route('tenants.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label for="name" class="form-label">Business name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $tenant->name) }}" required class="form-input" />
                <x-input-error :messages="$errors->get('name')" class="mt-1 text-xs" style="color:var(--red)" />
            </div>

            <div class="form-grid-2">
                <div>
                    <p class="form-label">Type</p>
                    <p class="mt-1 font-semibold" style="color:var(--text)">{{ ucfirst($tenant->business_type) }}</p>
                </div>
                <div>
                    <p class="form-label">Status</p>
                    <p class="mt-1 font-semibold" style="color:var(--text)">{{ ucfirst($tenant->status) }}</p>
                </div>
            </div>

            @can('update', $tenant)
                <div class="flex justify-end gap-2 border-t pt-4" style="border-color:var(--border)">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            @endcan
        </form>
    </div>
</x-app-layout>