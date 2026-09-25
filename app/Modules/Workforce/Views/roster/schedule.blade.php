@php($canManage = auth()->user()->hasPermissionTo('schedules.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Schedule</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Week of {{ $weekStart->format('M j, Y') }}</p>
        </div>
        <div class="flex gap-2">
            <a class="btn btn-sm btn-ghost" href="{{ route('staff.schedule', ['week' => $weekStart->subWeek()->toDateString()]) }}" aria-label="Previous week">←</a>
            <a class="btn btn-sm btn-ghost" href="{{ route('staff.schedule') }}">This week</a>
            <a class="btn btn-sm btn-ghost" href="{{ route('staff.schedule', ['week' => $weekStart->addWeek()->toDateString()]) }}" aria-label="Next week">→</a>
        </div>
    </div>
    @include('workforce::partials.nav')

    @foreach (['employee_id', 'starts_at', 'ends_at', 'date', 'start', 'end'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th>Employee</th>@foreach ($days as $day)<th>{{ $day->format('D j') }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td><strong style="color:var(--text)">{{ $employee->name }}</strong><br><small style="color:var(--text-3)">{{ $employee->position?->name }}</small></td>
                        @foreach ($days as $day)
                            @php($onLeave = $leave->first(fn ($l) => $l->employee_id === $employee->id && $l->starts_on->lte($day) && $l->ends_on->gte($day)))
                            <td class="text-sm">
                                @if ($onLeave)<span class="badge badge-amber">{{ ucfirst($onLeave->type) }} leave</span>@endif
                                @foreach ($shifts->get($employee->id.'|'.$day->toDateString(), collect()) as $shift)
                                    <div class="flex items-center gap-1">
                                        <span class="badge badge-blue">{{ $shift->label() }}</span>
                                        @if ($canManage)
                                            <form method="POST" action="{{ route('staff.shifts.cancel', $shift->id) }}">@csrf<button class="btn btn-sm btn-ghost" type="submit" aria-label="Cancel shift">×</button></form>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-sm" style="color:var(--text-3)">Add employees first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canManage && $employees->isNotEmpty())
        <form method="POST" action="{{ route('staff.shifts.store') }}" class="card p-6 mt-4 flex flex-wrap gap-2 items-end">
            @csrf
            <select name="employee_id" class="form-input" aria-label="Employee">
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ $weekStart->toDateString() }}" required class="form-input" aria-label="Date" />
            <input type="time" name="start" value="07:00" required class="form-input" aria-label="Start" />
            <input type="time" name="end" value="15:00" required class="form-input" aria-label="End" />
            <select name="property_id" class="form-input" aria-label="Property">
                <option value="">Any property</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}">{{ $property->name }}</option>
                @endforeach
            </select>
            <input type="text" name="notes" class="form-input" placeholder="Notes" aria-label="Notes" />
            <button type="submit" class="btn btn-primary">Add shift</button>
        </form>
    @endif
</x-app-layout>
