<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>My work</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $employee ? $employee->name.' · '.$employee->employee_no : 'Your account is not linked to an employee profile yet — ask a manager.' }}
            </p>
        </div>
        @if ($employee)
            <form method="POST" action="{{ route('my-work.clock') }}">
                @csrf
                <input type="hidden" name="action" value="{{ $employee->openAttendance ? 'out' : 'in' }}" />
                <button type="submit" class="btn {{ $employee->openAttendance ? 'btn-dark' : 'btn-primary' }}">
                    {{ $employee->openAttendance ? 'Clock out (in since '.$employee->openAttendance->clock_in_at->format('g:i A').')' : 'Clock in' }}
                </button>
            </form>
        @endif
    </div>
    @if (auth()->user()->hasPermissionTo('staff.view'))
        @include('workforce::partials.nav')
    @endif

    @foreach (['employee', 'type', 'starts_on', 'ends_on', 'leave'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="card p-6">
            <h2 class="text-lg">Shifts (next 7 days)</h2>
            <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                @forelse ($shifts as $shift)
                    <li>{{ $shift->starts_at->format('D, M j') }} · {{ $shift->label() }}{{ $shift->property ? ' · '.$shift->property->name : '' }}</li>
                @empty
                    <li style="color:var(--text-3)">No shifts.</li>
                @endforelse
            </ul>
        </div>
        <div class="card p-6">
            <h2 class="text-lg">Housekeeping</h2>
            <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                @forelse ($tasks as $task)
                    <li>Room {{ $task->room?->room_number }} · {{ $task->typeLabel() }} · due {{ $task->due_on->format('M j') }} @if ($task->priority === 'high')<span class="badge badge-amber">High</span>@endif</li>
                @empty
                    <li style="color:var(--text-3)">No rooms assigned.</li>
                @endforelse
            </ul>
            @if ($tasks->isNotEmpty())<a class="btn btn-sm btn-ghost mt-2" href="{{ route('housekeeping.index', ['mine' => 1]) }}">Open board</a>@endif
        </div>
        <div class="card p-6">
            <h2 class="text-lg">Maintenance</h2>
            <ul class="mt-2 text-sm space-y-1" style="color:var(--text-2)">
                @forelse ($tickets as $ticket)
                    <li><a href="{{ route('maintenance.show', $ticket->reference) }}">{{ $ticket->reference }}</a> · {{ $ticket->title }}{{ $ticket->room ? ' · room '.$ticket->room->room_number : '' }}</li>
                @empty
                    <li style="color:var(--text-3)">No tickets assigned.</li>
                @endforelse
            </ul>
        </div>
    </div>

    @if ($employee)
        <div class="grid grid-cols-3 gap-4 mt-4">
            <form method="POST" action="{{ route('my-work.leave.store') }}" class="card p-6 space-y-2">
                @csrf
                <h2 class="text-lg">Request leave</h2>
                <select name="type" class="form-input" aria-label="Leave type">
                    @foreach (\App\Modules\Workforce\Models\LeaveRequest::TYPES as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <input type="date" name="starts_on" required class="form-input" min="{{ today()->toDateString() }}" aria-label="From" />
                    <input type="date" name="ends_on" required class="form-input" min="{{ today()->toDateString() }}" aria-label="To" />
                </div>
                <input type="text" name="reason" class="form-input" placeholder="Reason (optional)" aria-label="Reason" />
                <button type="submit" class="btn btn-primary w-full">Send request</button>
            </form>
            <div class="card p-6" style="grid-column:span 2">
                <h2 class="text-lg">My leave</h2>
                <ul class="mt-2 text-sm space-y-2" style="color:var(--text-2)">
                    @forelse ($leave as $l)
                        <li class="flex justify-between items-center">
                            <span>{{ ucfirst($l->type) }} · {{ $l->starts_on->format('M j') }}–{{ $l->ends_on->format('M j') }} · {{ $l->statusLabel() }} {{ $l->decision_note ? '— '.$l->decision_note : '' }}</span>
                            @if ($l->status === 'pending')
                                <form method="POST" action="{{ route('my-work.leave.cancel', $l->id) }}">@csrf<button class="btn btn-sm btn-ghost" type="submit">Withdraw</button></form>
                            @endif
                        </li>
                    @empty
                        <li style="color:var(--text-3)">No requests.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
</x-app-layout>
