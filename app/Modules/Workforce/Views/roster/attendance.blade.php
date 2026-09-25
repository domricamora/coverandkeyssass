@php($canManage = auth()->user()->hasPermissionTo('attendance.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Attendance</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $date->format('l, M j, Y') }} · {{ $employees->filter(fn ($e) => $e->openAttendance)->count() }} on the clock now</p>
        </div>
        <form method="GET"><input type="date" name="date" value="{{ $date->toDateString() }}" class="form-input" onchange="this.form.submit()" aria-label="Date" /></form>
    </div>
    @include('workforce::partials.nav')
    <x-input-error :messages="$errors->get('employee')" />

    <div class="grid grid-cols-3 gap-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Employee</th><th>Shift</th><th>In</th><th>Out</th><th>Worked</th><th>Late</th></tr></thead>
                <tbody>
                    @forelse ($records as $a)
                        <tr>
                            <td>{{ $a->employee?->name }}</td>
                            <td style="color:var(--text-2)">{{ $a->shift?->label() ?? 'unscheduled' }}</td>
                            <td>{{ $a->clock_in_at->format('g:i A') }}</td>
                            <td>{{ $a->clock_out_at?->format('g:i A') ?? '—' }}</td>
                            <td>{{ $a->hoursLabel() }}</td>
                            <td>@if ($a->late_minutes)<span class="badge badge-amber">{{ $a->late_minutes }} min</span>@else — @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-sm" style="color:var(--text-3)">No clock-ins on this day.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canManage)
            <div class="card p-6">
                <h2 class="text-lg">Clock for someone</h2>
                <ul class="mt-2 text-sm space-y-2">
                    @foreach ($employees as $employee)
                        <li class="flex justify-between items-center">
                            <span>{{ $employee->name }} @if ($employee->openAttendance)<span class="badge badge-green">in since {{ $employee->openAttendance->clock_in_at->format('g:i A') }}</span>@endif</span>
                            <form method="POST" action="{{ route('staff.attendance.clock', $employee->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $employee->openAttendance ? 'out' : 'in' }}" />
                                <button type="submit" class="btn btn-sm btn-ghost">Clock {{ $employee->openAttendance ? 'out' : 'in' }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
