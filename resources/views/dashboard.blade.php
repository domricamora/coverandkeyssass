<x-app-layout>
    <div class="dash-row-head">
        <div>
            <p class="dash-kicker">{{ ucfirst($tenant->business_type) }}</p>
            <h1 class="mt-1">{{ $tenant->name }}</h1>
        </div>
        <span class="badge {{ $tenant->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ ucfirst($tenant->status) }}</span>
    </div>

    <div class="stat-grid stat-grid--4">
        <div class="stat card">
            <p class="stat__label">Team members</p>
            <p class="stat__value">{{ $members->count() }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Listings</p>
            <p class="stat__value">{{ $listings }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Upcoming reservations</p>
            <p class="stat__value">{{ \App\Modules\Booking\Models\Booking::query()->whereIn('status', ['pending', 'held', 'confirmed'])->where('check_in', '>=', today()->toDateString())->count() }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Active modules</p>
            <p class="stat__value">{{ $activeModules->count() }}</p>
        </div>
    </div>

    <div class="overview-grid">
        <section class="card overview-card">
            @php($done = collect($steps)->where('done', true)->count())
            <div class="overview-card__head">
                <h2 class="dash-h2">Get set up</h2>
                <span class="muted">{{ $done }} of {{ count($steps) }} done</span>
            </div>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $done }}" aria-label="Setup progress">
                <span style="transform: scaleX({{ $done / max(count($steps), 1) }})"></span>
            </div>
            <ol class="checklist">
                @foreach ($steps as $step)
                    <li class="checklist__item {{ $step['done'] ? 'is-done' : '' }}">
                        <span class="checklist__mark" aria-hidden="true">
                            @if ($step['done'])
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            @endif
                        </span>
                        <span class="checklist__label">{{ $step['label'] }}<span class="sr-only">{{ $step['done'] ? ' (done)' : '' }}</span></span>
                        @unless ($step['done'])
                            @if (Route::has($step['route']))
                                <a class="btn btn-outline btn-sm" href="{{ route($step['route']) }}">Start</a>
                            @endif
                        @endunless
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="card overview-card">
            <h2 class="dash-h2">Your access</h2>
            <dl class="kv">
                <div>
                    <dt>Role</dt>
                    <dd>{{ $userRole?->display_name ?? 'None' }}</dd>
                </div>
                <div>
                    <dt>Account status</dt>
                    <dd>{{ ucfirst(auth()->user()->status) }}</dd>
                </div>
                <div>
                    <dt>Currency</dt>
                    <dd>{{ $tenant->currency }}</dd>
                </div>
                <div>
                    <dt>Modules</dt>
                    <dd>{{ $activeModules->pluck('module.name')->implode(', ') ?: 'Foundation only' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</x-app-layout>
