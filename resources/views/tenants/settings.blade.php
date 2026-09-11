<x-app-layout>
    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-semibold text-brand-950 dark:text-sand-50">Business settings</h1>
        <p class="mt-1 text-sm text-brand-600 dark:text-brand-300">{{ $tenant->name }} · {{ $tenant->slug }}</p>

        <form method="POST" action="{{ route('tenants.update') }}" class="hoso-card mt-6 space-y-5 p-6">
            @csrf
            @method('PATCH')
            <div>
                <label for="name" class="hoso-label">Business name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $tenant->name) }}" required class="hoso-input" />
                <x-input-error :messages="$errors->get('name')" class="hoso-error" />
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm text-brand-700 dark:text-brand-300">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Type</p>
                    <p class="mt-1 font-semibold text-brand-900 dark:text-sand-50">{{ ucfirst($tenant->business_type) }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Status</p>
                    <p class="mt-1 font-semibold text-brand-900 dark:text-sand-50">{{ ucfirst($tenant->status) }}</p>
                </div>
            </div>

            @can('update', $tenant)
                <div class="flex justify-end gap-2 border-t border-sand-100 pt-4 dark:border-brand-800">
                    <button type="submit" class="hoso-btn-primary">Save changes</button>
                </div>
            @endcan
        </form>
    </div>
</x-app-layout>
