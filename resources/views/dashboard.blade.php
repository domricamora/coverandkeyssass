<x-app-layout>
    <div class="dash-row-head">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest" style="color:var(--text-3)">{{ ucfirst($tenant->business_type) }} &middot; Overview</p>
            <h1 class="mt-1">{{ $tenant->name }}</h1>
        </div>
        <span class="badge badge-amber">Foundation phase</span>
    </div>

    <div class="stat-grid stat-grid--3">
        <div class="stat card">
            <p class="stat__label">Team members</p>
            <p class="stat__value">{{ $members->count() }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Properties</p>
            <p class="stat__value">{{ \App\Modules\Marketplace\Models\Property::query()->count() }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Upcoming reservations</p>
            <p class="stat__value">{{ \App\Modules\Booking\Models\Booking::query()->whereIn('status', ['pending', 'held', 'confirmed'])->where('check_in', '>=', today()->toDateString())->count() }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card p-6 lg:col-span-3">
            <h2 class="dash-h2" style="margin-top:0">Set up your business</h2>
            <ol class="mt-4 space-y-3 text-sm" style="color:var(--text-2)">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold text-white" style="background:var(--gold)">1</span>
                    <span>Invite your team on the <a href="{{ route('team') }}" class="font-semibold underline underline-offset-4">Team page</a> and give each member the right role.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold text-white" style="background:var(--gold)">2</span>
                    <span>Review <a href="{{ route('tenants.settings') }}" class="font-semibold underline underline-offset-4">business settings</a> — name and type are editable.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold text-white" style="background:var(--gold)">3</span>
                    <span>Properties, rooms and rates unlock in the next build phases.</span>
                </li>
            </ol>
        </div>

        <div class="card p-6 lg:col-span-2">
            <h2 class="dash-h2" style="margin-top:0">Your access</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-2">
                    <dt style="color:var(--text-3)">Role</dt>
                    <dd class="font-semibold" style="color:var(--text)">{{ $userRole?->display_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt style="color:var(--text-3)">Account status</dt>
                    <dd class="font-semibold" style="color:var(--text)">{{ ucfirst(auth()->user()->status) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt style="color:var(--text-3)">Currency</dt>
                    <dd class="font-semibold" style="color:var(--text)">{{ $tenant->currency }}</dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>