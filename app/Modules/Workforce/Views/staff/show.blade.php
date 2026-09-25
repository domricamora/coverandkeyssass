@php($canManage = auth()->user()->hasPermissionTo('staff.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $employee->name }} <small style="color:var(--text-3)">{{ $employee->employee_no }}</small></h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $employee->position?->name ?? 'No position' }} · {{ $employee->department?->name ?? 'No department' }} ·
                {{ $employee->user ? 'Access: '.($role ?? 'no role') : 'No login account' }}
                @if ($employee->user && auth()->user()->hasPermissionTo('team.manage')) · <a href="{{ route('team') }}">manage access</a>@endif
            </p>
        </div>
        <a href="{{ route('staff.index') }}" class="btn btn-ghost">All staff</a>
    </div>
    @include('workforce::partials.nav')

    @foreach (['name', 'user_id', 'status'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="space-y-4" style="grid-column:span 2">
            <div class="card p-6">
                <h2 class="text-lg">Upcoming shifts</h2>
                <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                    @forelse ($shifts as $shift)
                        <li>{{ $shift->starts_at->format('D, M j') }} · {{ $shift->label() }}</li>
                    @empty
                        <li style="color:var(--text-3)">None scheduled.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card p-6">
                <h2 class="text-lg">Attendance</h2>
                <table class="table mt-2">
                    <thead><tr><th>In</th><th>Out</th><th>Worked</th><th>Late</th></tr></thead>
                    <tbody>
                        @forelse ($attendances as $a)
                            <tr><td>{{ $a->clock_in_at->format('M j, g:i A') }}</td><td>{{ $a->clock_out_at?->format('g:i A') ?? '—' }}</td><td>{{ $a->hoursLabel() }}</td><td>{{ $a->late_minutes ? $a->late_minutes.' min' : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-sm" style="color:var(--text-3)">No records.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card p-6">
                <h2 class="text-lg">Leave</h2>
                <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                    @forelse ($leave as $l)
                        <li>{{ ucfirst($l->type) }} · {{ $l->starts_on->format('M j') }}–{{ $l->ends_on->format('M j') }} ({{ $l->days() }}d) · {{ $l->statusLabel() }}</li>
                    @empty
                        <li style="color:var(--text-3)">No requests.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        @if ($canManage)
            <form method="POST" action="{{ route('staff.update', $employee->id) }}" class="card p-6 space-y-2">
                @csrf
                @method('PATCH')
                <h2 class="text-lg">Profile</h2>
                <input name="name" type="text" required value="{{ old('name', $employee->name) }}" class="form-input" aria-label="Name" />
                <input name="email" type="email" value="{{ old('email', $employee->email) }}" class="form-input" placeholder="Email" aria-label="Email" />
                <input name="phone" type="text" value="{{ old('phone', $employee->phone) }}" class="form-input" placeholder="Phone" aria-label="Phone" />
                @include('workforce::staff.fields')
                <select name="status" class="form-input" aria-label="Status">
                    <option value="active" @selected($employee->status === 'active')>Active</option>
                    <option value="terminated" @selected($employee->status === 'terminated')>Terminated</option>
                </select>
                <button type="submit" class="btn btn-primary w-full">Save</button>
            </form>
        @endif
    </div>
</x-app-layout>
