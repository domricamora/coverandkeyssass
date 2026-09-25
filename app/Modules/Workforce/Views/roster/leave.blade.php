@php($canApprove = auth()->user()->hasPermissionTo('leave.approve'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Leave</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $pending->count() }} request(s) waiting.</p>
        </div>
    </div>
    @include('workforce::partials.nav')
    <x-input-error :messages="$errors->get('leave')" />

    <div class="card p-6">
        <h2 class="text-lg">Pending</h2>
        @forelse ($pending as $l)
            <div class="mt-3 pt-3 flex justify-between gap-3 text-sm" style="border-top:1px solid var(--border);color:var(--text-2)">
                <div>
                    <strong style="color:var(--text)">{{ $l->employee?->name }}</strong> · {{ ucfirst($l->type) }} · {{ $l->starts_on->format('M j') }}–{{ $l->ends_on->format('M j, Y') }} ({{ $l->days() }} day(s))
                    @if ($l->reason)<br><small>{{ $l->reason }}</small>@endif
                </div>
                @if ($canApprove)
                    <div class="flex gap-1 items-start">
                        <form method="POST" action="{{ route('staff.leave.decide', $l->id) }}">@csrf<input type="hidden" name="approve" value="1" /><button class="btn btn-sm btn-primary" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('staff.leave.decide', $l->id) }}" class="flex gap-1">
                            @csrf
                            <input type="hidden" name="approve" value="0" />
                            <input name="note" type="text" class="form-input" placeholder="Reason" style="width:140px" aria-label="Rejection reason" />
                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <p class="mt-2 text-sm" style="color:var(--text-3)">Nothing to decide.</p>
        @endforelse
    </div>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th>Employee</th><th>Leave</th><th>Dates</th><th>Decision</th></tr></thead>
            <tbody>
                @foreach ($recent as $l)
                    <tr>
                        <td>{{ $l->employee?->name }}</td>
                        <td>{{ ucfirst($l->type) }}</td>
                        <td>{{ $l->starts_on->format('M j') }}–{{ $l->ends_on->format('M j') }}</td>
                        <td><span class="badge {{ $l->status === 'approved' ? 'badge-green' : 'badge-gray' }}">{{ $l->statusLabel() }}</span> {{ $l->decision_note }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
