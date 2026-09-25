<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Audit log', 'sub' => 'Every privileged action, with who did it and where from.'])

    <form method="GET" class="admin-search mt-4">
        <select name="action" class="form-input" aria-label="Area" style="max-width:180px">
            <option value="">Any area</option>
            @foreach ($prefixes as $prefix)<option value="{{ $prefix }}" @selected(request('action') === $prefix)>{{ $prefix }}</option>@endforeach
        </select>
        <input type="text" name="user" value="{{ request('user') }}" placeholder="User email…" class="form-input" aria-label="User email" />
        <input type="number" name="tenant" value="{{ request('tenant') }}" placeholder="Business ID" class="form-input" aria-label="Business ID" style="max-width:130px" />
        <input type="date" name="from" value="{{ request('from') }}" class="form-input" aria-label="From date" />
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
    </form>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">When</th><th scope="col">Action</th><th scope="col">Who</th><th scope="col">Business</th><th scope="col">Details</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td><small>{{ $log->created_at->format('M j, Y g:i A') }}</small></td>
                        <td><span class="font-mono text-xs">{{ $log->action }}</span></td>
                        <td>{{ $log->user?->name ?? 'system' }}<br><small>{{ $log->ip_address ?? '' }}</small></td>
                        <td>{{ $log->tenant?->name ?? '—' }}</td>
                        <td><small class="font-mono" style="word-break:break-all">{{ \Illuminate\Support\Str::limit(json_encode($log->new_values ?? []), 160) }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color:var(--text-3)">No entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-app-layout>
