@php
    $user = auth()->user();
    $canManage = $user->hasPermissionTo('maintenance.manage');
    $canWork = $user->hasPermissionTo('maintenance.work');
    $closed = $ticket->status === 'closed';
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $ticket->reference }} · {{ $ticket->title }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $ticket->property?->name }}{{ $ticket->room ? ' · room '.$ticket->room->room_number.' ('.\Illuminate\Support\Str::headline($ticket->room->housekeeping_status).')' : '' }}
                · {{ ucfirst($ticket->category) }} · {{ ucfirst($ticket->priority) }} priority · reported by {{ $ticket->reporter?->name ?? '—' }} {{ $ticket->created_at->diffForHumans() }}
            </p>
        </div>
        <a href="{{ route('maintenance.index') }}" class="btn btn-ghost">All tickets</a>
    </div>

    @foreach (['status', 'assigned_to', 'cost', 'body', 'file'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="card p-6" style="grid-column:span 2">
            @if ($ticket->description)<p class="text-sm" style="color:var(--text-2)">{!! nl2br(e($ticket->description)) !!}</p>@endif

            <h2 class="text-lg mt-4">Notes</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($ticket->notes as $note)
                    <li style="color:{{ $note->is_system ? 'var(--text-3)' : 'var(--text-2)' }}">
                        <strong>{{ $note->author?->name ?? 'System' }}</strong> · {{ $note->created_at->format('M j, g:i A') }}<br>
                        {!! $note->is_system ? '<em>'.e($note->body).'</em>' : nl2br(e($note->body)) !!}
                    </li>
                @endforeach
            </ul>
            @if ($canWork && ! $closed)
                <form method="POST" action="{{ route('maintenance.notes.store', $ticket->reference) }}" class="mt-3 space-y-2">
                    @csrf
                    <textarea name="body" rows="2" required class="form-input" placeholder="Add a note" aria-label="Note"></textarea>
                    <button type="submit" class="btn btn-sm btn-ghost">Add note</button>
                </form>
            @endif

            <h2 class="text-lg mt-6">Attachments</h2>
            <ul class="mt-2 text-sm space-y-1">
                @forelse ($ticket->attachments as $file)
                    <li><a href="{{ route('maintenance.attachments.show', [$ticket->reference, $file->id]) }}" target="_blank" rel="noopener">{{ $file->alt ?? 'File' }}</a> <small style="color:var(--text-3)">({{ $file->kind }})</small></li>
                @empty
                    <li style="color:var(--text-3)">No files.</li>
                @endforelse
            </ul>
            @if ($canWork && ! $closed)
                <form method="POST" action="{{ route('maintenance.attachments.store', $ticket->reference) }}" enctype="multipart/form-data" class="mt-2 flex gap-2">
                    @csrf
                    <input type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-input" aria-label="Attachment" />
                    <button type="submit" class="btn btn-sm btn-ghost">Upload</button>
                </form>
            @endif
        </div>

        <div class="space-y-4">
            <div class="card p-6 text-sm space-y-2" style="color:var(--text-2)">
                <p><span class="badge badge-gray">{{ $ticket->statusLabel() }}</span> @if ($ticket->room_out_of_order)<span class="badge badge-red">Room out of order</span>@endif</p>
                <p><strong>Assigned</strong> {{ $ticket->assignee?->name ?? 'nobody' }}</p>
                <p><strong>Cost</strong> {{ $ticket->cost !== null ? '₱'.number_format((float) $ticket->cost, 2) : '—' }}</p>
                @if ($ticket->started_at)<p><strong>Started</strong> {{ $ticket->started_at->format('M j, g:i A') }}</p>@endif
                @if ($ticket->resolved_at)<p><strong>Resolved</strong> {{ $ticket->resolved_at->format('M j, g:i A') }}</p>@endif

                @if ($canWork)
                    <div class="flex flex-wrap gap-1 pt-2">
                        @foreach (\App\Modules\Maintenance\Models\MaintenanceTicket::TRANSITIONS[$ticket->status] ?? [] as $to)
                            @continue($to === 'closed' && ! $canManage)
                            <form method="POST" action="{{ route('maintenance.transition', $ticket->reference) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $to }}" />
                                <button type="submit" class="btn btn-sm {{ $to === 'resolved' ? 'btn-primary' : 'btn-ghost' }}">{{ $ticket->status === 'resolved' && $to === 'in_progress' ? 'Reopen' : \Illuminate\Support\Str::headline($to) }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($canManage && ! $closed)
                <form method="POST" action="{{ route('maintenance.assign', $ticket->reference) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Assign</h2>
                    <select name="assigned_to" class="form-input" aria-label="Assign to">
                        <option value="">Nobody</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </form>
                <form method="POST" action="{{ route('maintenance.cost', $ticket->reference) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Cost</h2>
                    <input name="cost" type="number" step="0.01" min="0" value="{{ $ticket->cost }}" required class="form-input" aria-label="Cost" />
                    <button type="submit" class="btn btn-sm btn-primary">Save cost</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
