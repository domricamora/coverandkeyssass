@php($canManage = auth()->user()->hasPermissionTo('staff.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Staff</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $employees->where('status', 'active')->count() }} active employees · {{ $departments->count() }} departments</p>
        </div>
    </div>
    @include('workforce::partials.nav')

    @foreach (['name', 'user_id', 'department_id', 'position_id', 'employment_type'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Employee</th><th>Department</th><th>Position</th><th>Type</th><th>Account</th></tr></thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr style="{{ $employee->status === 'terminated' ? 'opacity:.55' : '' }}">
                            <td><a href="{{ route('staff.show', $employee->id) }}"><strong>{{ $employee->name }}</strong></a><br><small style="color:var(--text-3)">{{ $employee->employee_no }}{{ $employee->status === 'terminated' ? ' · terminated' : '' }}</small></td>
                            <td style="color:var(--text-2)">{{ $employee->department?->name ?? '—' }}</td>
                            <td style="color:var(--text-2)">{{ $employee->position?->name ?? '—' }}</td>
                            <td style="color:var(--text-2)">{{ \Illuminate\Support\Str::headline($employee->employment_type) }}</td>
                            <td style="color:var(--text-2)">{{ $employee->user?->email ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No employees yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            @if ($canManage)
                <form method="POST" action="{{ route('staff.store') }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Add employee</h2>
                    <input name="name" type="text" required class="form-input" placeholder="Full name" aria-label="Full name" />
                    <input name="email" type="email" class="form-input" placeholder="Email" aria-label="Email" />
                    <input name="phone" type="text" class="form-input" placeholder="Phone" aria-label="Phone" />
                    @include('workforce::staff.fields', ['employee' => null])
                    <button type="submit" class="btn btn-primary w-full">Add employee</button>
                </form>
            @endif

            <div class="card p-6">
                <h2 class="text-lg">Departments</h2>
                <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                    @foreach ($departments as $department)
                        <li><a href="{{ route('staff.index', ['department' => $department->id]) }}">{{ $department->name }}</a> · {{ $department->employees_count }}</li>
                    @endforeach
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('staff.departments.store') }}" class="flex gap-2 mt-3">
                        @csrf
                        <input name="name" type="text" required class="form-input" placeholder="Housekeeping" aria-label="Department name" />
                        <button type="submit" class="btn btn-sm btn-ghost">Add</button>
                    </form>
                @endif
            </div>

            <div class="card p-6">
                <h2 class="text-lg">Positions</h2>
                <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                    @foreach ($positions as $position)
                        <li>{{ $position->name }} {{ $position->department ? '· '.$position->department->name : '' }} {{ $position->hourly_rate ? '· ₱'.number_format((float) $position->hourly_rate, 2).'/h' : '' }}</li>
                    @endforeach
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('staff.positions.store') }}" class="space-y-2 mt-3">
                        @csrf
                        <input name="name" type="text" required class="form-input" placeholder="Room Attendant" aria-label="Position name" />
                        <div class="flex gap-2">
                            <select name="department_id" class="form-input" aria-label="Department">
                                <option value="">No department</option>
                                @foreach ($allDepartments as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                            <input name="hourly_rate" type="number" step="0.01" min="0" class="form-input" placeholder="₱/h" aria-label="Hourly rate" />
                        </div>
                        <button type="submit" class="btn btn-sm btn-ghost">Add position</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
