<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">{{ $tenant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ ucfirst($tenant->business_type) }} &middot; {{ $tenant->slug }} &middot; {{ $tenant->users_count }} member(s)</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.tenants.verify', $tenant) }}" class="flex gap-1">
                @csrf
                <input type="hidden" name="verified" value="{{ $tenant->verified_at ? 0 : 1 }}" />
                @unless ($tenant->verified_at)<input name="note" class="form-input" placeholder="Checked: permit, ID…" aria-label="Verification note" style="max-width:180px" />@endunless
                <button type="submit" class="btn btn-sm {{ $tenant->verified_at ? 'btn-ghost' : 'btn-primary' }}">{{ $tenant->verified_at ? 'Verified '.$tenant->verified_at->format('M j, Y').' · undo' : 'Verify host' }}</button>
            </form>
            @if ($tenant->status === 'active')
                <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}">
                    @csrf
                    <button type="submit" class="btn btn-dark btn-sm">Suspend</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.tenants.activate', $tenant) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}"
                  onsubmit="return confirm('Delete this tenant? Its members lose access immediately.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
        </div>
    </div>

    <div class="table-wrap card mt-6">
        <table class="table">
            <thead>
                <tr><th>Member</th><th>Email</th><th>Account status</th></tr>
            </thead>
            <tbody>
                @forelse ($members as $membership)
                    <tr>
                        <td class="font-semibold" style="color:var(--text)">{{ $membership->user->name }}</td>
                        <td style="color:var(--text-3)">{{ $membership->user->email }}</td>
                        <td style="color:var(--text-2)">{{ ucfirst($membership->user->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-sm" style="color:var(--text-3)">No members.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('admin.tenants.index') }}" class="mt-6 inline-flex items-center gap-1 text-sm font-semibold" style="color:var(--text-2)">&larr; Back to tenants</a>
</x-app-layout>