<x-app-layout>
    <div class="mx-auto max-w-3xl">
        <div class="flex items-end justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-brand-950 dark:text-sand-50">Your businesses</h1>
                <p class="mt-1 text-sm text-brand-600 dark:text-brand-300">Select a business to work in, or create a new one.</p>
            </div>
            <a href="{{ route('tenants.create') }}" class="hoso-btn-primary">+ New business</a>
        </div>

        @if ($tenants->isEmpty())
            <div class="hoso-card mt-8 p-10 text-center">
                <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-100 text-brand-700 dark:bg-brand-800 dark:text-brand-200">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                </div>
                <h2 class="mt-4 font-display text-xl font-semibold text-brand-900 dark:text-sand-50">No businesses yet</h2>
                <p class="mx-auto mt-2 max-w-sm text-sm text-brand-600 dark:text-brand-300">
                    Create your first business account — a hotel, resort, guesthouse or restaurant — to get started.
                </p>
                <a href="{{ route('tenants.create') }}" class="hoso-btn-primary mt-6">Create your first business</a>
            </div>
        @else
            <ul class="mt-8 space-y-3">
                @foreach ($tenants as $tenant)
                    <li class="hoso-card flex items-center justify-between gap-4 p-5">
                        <div class="min-w-0">
                            <p class="truncate font-display text-lg font-semibold text-brand-900 dark:text-sand-50">{{ $tenant->name }}</p>
                            <p class="mt-0.5 text-xs uppercase tracking-widest text-brand-500 dark:text-brand-400">{{ $tenant->business_type }} · {{ $tenant->slug }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($currentTenantId === $tenant->id)
                                <span class="hoso-badge bg-brand-600 text-white">Active</span>
                            @endif
                            <form method="POST" action="{{ route('tenants.switch', $tenant) }}">
                                @csrf
                                <button type="submit" class="hoso-btn-secondary">
                                    {{ $currentTenantId === $tenant->id ? 'Open' : 'Select' }}
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
