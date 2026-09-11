<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">{{ ucfirst($tenant->business_type) }} · Overview</p>
                <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-brand-950 dark:text-sand-50">{{ $tenant->name }}</h1>
            </div>
            <span class="hoso-badge bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">Foundation phase</span>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="hoso-card p-5">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Team members</p>
                <p class="mt-2 font-display text-3xl font-semibold text-brand-900 dark:text-sand-50">{{ $members->count() }}</p>
            </div>
            <div class="hoso-card p-5">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Properties</p>
                <p class="mt-2 font-display text-3xl font-semibold text-brand-900 dark:text-sand-50">—</p>
                <p class="mt-1 text-xs text-brand-500 dark:text-brand-400">Arrives with Property Management (Phase 04)</p>
            </div>
            <div class="hoso-card p-5">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-400">Reservations</p>
                <p class="mt-2 font-display text-3xl font-semibold text-brand-900 dark:text-sand-50">—</p>
                <p class="mt-1 text-xs text-brand-500 dark:text-brand-400">Arrives with the Booking Engine (Phase 05)</p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-5">
            <div class="hoso-card p-6 lg:col-span-3">
                <h2 class="font-display text-lg font-semibold text-brand-900 dark:text-sand-50">Set up your business</h2>
                <ol class="mt-4 space-y-3 text-sm text-brand-700 dark:text-brand-200">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600 text-[11px] font-bold text-white">1</span>
                        <span>Invite your team on the <a href="{{ route('team') }}" class="font-semibold underline underline-offset-4">Team page</a> and give each member the right role.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600 text-[11px] font-bold text-white">2</span>
                        <span>Review <a href="{{ route('tenants.settings') }}" class="font-semibold underline underline-offset-4">business settings</a> — name and type are editable.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-600 text-[11px] font-bold text-white">3</span>
                        <span>Properties, rooms and rates unlock in the next build phases.</span>
                    </li>
                </ol>
            </div>

            <div class="hoso-card p-6 lg:col-span-2">
                <h2 class="font-display text-lg font-semibold text-brand-900 dark:text-sand-50">Your access</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-brand-500 dark:text-brand-400">Role</dt>
                        <dd class="font-semibold text-brand-900 dark:text-sand-50">{{ $userRole?->display_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-brand-500 dark:text-brand-400">Account status</dt>
                        <dd class="font-semibold text-brand-900 dark:text-sand-50">{{ ucfirst(auth()->user()->status) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-brand-500 dark:text-brand-400">Currency</dt>
                        <dd class="font-semibold text-brand-900 dark:text-sand-50">{{ $tenant->currency }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
