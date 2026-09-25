@php
    use App\Modules\PropertyManagement\Models\Room;
    $user = auth()->user();
    $canManage = $user->hasPermissionTo('housekeeping.manage');
    $canWork = $user->hasPermissionTo('housekeeping.work');
    $badge = fn ($s) => match ($s) {
        Room::HK_INSPECTED, Room::HK_CLEAN => 'badge-green',
        Room::HK_CLEANING => 'badge-blue',
        Room::HK_DIRTY => 'badge-amber',
        default => 'badge-gray',
    };
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Housekeeping</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                @if ($property)
                    {{ $property->name }} · {{ $rooms->where('housekeeping_status', Room::HK_DIRTY)->count() }} dirty ·
                    {{ $rooms->whereIn('housekeeping_status', [Room::HK_CLEAN, Room::HK_INSPECTED])->count() }} ready ·
                    {{ $rooms->where('housekeeping_status', Room::HK_OUT_OF_ORDER)->count() }} out of order
                @else
                    Add a property first.
                @endif
            </p>
        </div>
        <form method="GET" class="flex gap-2 items-center">
            <select name="property" class="form-input" onchange="this.form.submit()" aria-label="Property">
                @foreach ($properties as $p)
                    <option value="{{ $p->slug }}" @selected($property?->id === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <label class="text-sm flex items-center gap-1"><input type="checkbox" name="mine" value="1" @checked($mine) onchange="this.form.submit()" /> My tasks</label>
        </form>
    </div>

    @foreach (['task', 'type', 'assigned_to', 'housekeeping_status', 'title', 'room_id'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    {{-- Room status grid --}}
    <div class="grid grid-cols-4 gap-3 mt-4">
        @foreach ($rooms as $room)
            <div class="card p-3 text-sm" x-data="{ open: false }">
                <div class="flex justify-between items-center">
                    <strong style="color:var(--text)">{{ $room->room_number }}</strong>
                    <span class="badge {{ $badge($room->housekeeping_status) }}">{{ \Illuminate\Support\Str::headline($room->housekeeping_status) }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-ghost mt-2" @click="open = !open">Actions</button>
                <div x-show="open" class="space-y-2 mt-2">
                    @if ($canManage)
                        <form method="POST" action="{{ route('housekeeping.rooms.status', $room->id) }}" class="flex gap-1">
                            @csrf
                            <select name="housekeeping_status" class="form-input" aria-label="Status of room {{ $room->room_number }}">
                                @foreach (Room::HK_STATUSES as $status)
                                    <option value="{{ $status }}" @selected($room->housekeeping_status === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-ghost">Set</button>
                        </form>
                    @endif
                    @if ($canWork)
                        <form method="POST" action="{{ route('housekeeping.rooms.issue', $room->id) }}" class="space-y-1">
                            @csrf
                            <input name="title" type="text" required class="form-input" placeholder="Report issue (e.g. leaking tap)" aria-label="Issue in room {{ $room->room_number }}" />
                            <label class="flex items-center gap-1"><input type="checkbox" name="out_of_order" value="1" /> Take out of order</label>
                            <button type="submit" class="btn btn-sm btn-danger">Report</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-3 gap-4 mt-6">
        <div class="card p-6" style="grid-column:span 2">
            <h2 class="text-lg">Tasks</h2>
            @forelse ($tasks as $task)
                <div class="mt-3 pt-3 flex justify-between gap-3 text-sm" style="border-top:1px solid var(--border);color:var(--text-2)">
                    <div>
                        <strong style="color:var(--text)">Room {{ $task->room?->room_number }}</strong> · {{ $task->typeLabel() }}
                        @if ($task->priority === 'high')<span class="badge badge-amber">High</span>@endif
                        <span class="badge badge-gray">{{ \Illuminate\Support\Str::headline($task->status) }}{{ $task->status === 'completed' ? ' · awaiting inspection' : '' }}</span>
                        <br><small style="color:var(--text-3)">Due {{ $task->due_on->format('M j') }} · {{ $task->assignee?->name ?? 'Unassigned' }}</small>
                        @if ($task->notes)<br><small>{{ $task->notes }}</small>@endif
                    </div>
                    <div class="flex gap-1 flex-wrap justify-end items-start">
                        @if ($canManage && in_array($task->status, ['pending', 'in_progress'], true))
                            <form method="POST" action="{{ route('housekeeping.tasks.assign', $task->id) }}">
                                @csrf
                                <select name="assigned_to" class="form-input" onchange="this.form.submit()" aria-label="Assign task for room {{ $task->room?->room_number }}">
                                    <option value="">Unassigned</option>
                                    @foreach ($members as $member)
                                        <option value="{{ $member->id }}" @selected($task->assigned_to === $member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                        @if ($canWork && $task->status === 'pending')
                            <form method="POST" action="{{ route('housekeeping.tasks.start', $task->id) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Start</button></form>
                        @elseif ($canWork && $task->status === 'in_progress')
                            <form method="POST" action="{{ route('housekeeping.tasks.complete', $task->id) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Done</button></form>
                        @elseif ($canManage && $task->status === 'completed')
                            <form method="POST" action="{{ route('housekeeping.tasks.inspect', $task->id) }}">@csrf<input type="hidden" name="passed" value="1" /><button class="btn btn-sm btn-primary" type="submit">Pass</button></form>
                            <form method="POST" action="{{ route('housekeeping.tasks.inspect', $task->id) }}" class="flex gap-1">
                                @csrf
                                <input type="hidden" name="passed" value="0" />
                                <input name="notes" type="text" class="form-input" placeholder="What's wrong?" style="width:140px" aria-label="Inspection notes" />
                                <button class="btn btn-sm btn-danger" type="submit">Fail</button>
                            </form>
                        @endif
                        @if ($canManage && in_array($task->status, ['pending', 'in_progress'], true))
                            <form method="POST" action="{{ route('housekeeping.tasks.cancel', $task->id) }}">@csrf<button class="btn btn-sm btn-ghost" type="submit">Cancel</button></form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="mt-2 text-sm" style="color:var(--text-3)">Nothing to clean right now.</p>
            @endforelse
        </div>

        <div class="space-y-4">
            @if ($canManage && $property)
                <form method="POST" action="{{ route('housekeeping.tasks.store') }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">New task</h2>
                    <select name="room_id" class="form-input" aria-label="Room">
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}">Room {{ $room->room_number }}</option>
                        @endforeach
                    </select>
                    <select name="type" class="form-input" aria-label="Task type">
                        @foreach (\App\Modules\Housekeeping\Models\HousekeepingTask::TYPES as $type)
                            <option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>
                        @endforeach
                    </select>
                    <input name="due_on" type="date" value="{{ today()->toDateString() }}" required class="form-input" aria-label="Due date" />
                    <select name="assigned_to" class="form-input" aria-label="Assign to">
                        <option value="">Unassigned</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                    <label class="text-sm flex items-center gap-1"><input type="checkbox" name="priority" value="high" /> High priority</label>
                    <textarea name="notes" rows="2" class="form-input" placeholder="Notes" aria-label="Notes"></textarea>
                    <button type="submit" class="btn btn-primary w-full">Create task</button>
                </form>
            @endif

            <div class="card p-6">
                <h2 class="text-lg">Open maintenance</h2>
                <ul class="mt-2 text-sm space-y-2" style="color:var(--text-2)">
                    @forelse ($tickets as $ticket)
                        <li><strong>{{ $ticket->reference }}</strong> · room {{ $ticket->room?->room_number ?? '—' }} · {{ $ticket->title }} <span class="badge badge-gray">{{ $ticket->statusLabel() }}</span></li>
                    @empty
                        <li style="color:var(--text-3)">No open tickets.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
