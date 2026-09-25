<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Review moderation</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $flagged }} reported by hosts. Verified reviews go live at once; hide the ones that break the rules.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.reviews.index', ['flagged' => 1]) }}" class="btn btn-sm {{ request()->boolean('flagged') ? 'btn-dark' : 'btn-ghost' }}">Reported</a>
            <a href="{{ route('admin.reviews.index', ['status' => 'rejected']) }}" class="btn btn-sm {{ request('status') === 'rejected' ? 'btn-dark' : 'btn-ghost' }}">Hidden</a>
            <a href="{{ route('admin.reviews.index') }}" class="btn btn-sm btn-ghost">All</a>
        </div>
    </div>

    <div class="table-wrap card">
        <table class="table">
            <thead><tr><th>Review</th><th>Listing</th><th>Status</th><th class="text-right">Action</th></tr></thead>
            <tbody>
                @forelse ($reviews as $review)
                    <tr>
                        <td>
                            <strong>{{ $review->user?->name }}</strong> · {{ $review->rating }} ★ · {{ $review->created_at->format('M j, Y') }}<br>
                            <span style="color:var(--text-2)">{{ \Illuminate\Support\Str::limit($review->comment, 220) }}</span>
                            @if ($review->flagged_at)<br><small class="badge badge-amber">Reported: {{ $review->flag_reason }}</small>@endif
                            @if ($review->moderation_note)<br><small style="color:var(--text-3)">Note: {{ $review->moderation_note }}</small>@endif
                        </td>
                        <td>{{ $review->reviewable?->name }}</td>
                        <td><span class="badge {{ $review->status === 'published' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($review->status) }}</span></td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.reviews.moderate', $review->id) }}" class="flex gap-1 justify-end">
                                @csrf
                                <input name="note" type="text" class="form-input" placeholder="Note" style="width:120px" aria-label="Moderation note" />
                                @if ($review->status !== 'published' || $review->flagged_at)
                                    <button name="publish" value="1" type="submit" class="btn btn-sm btn-primary">Keep / publish</button>
                                @endif
                                @if ($review->status !== 'rejected')
                                    <button name="publish" value="0" type="submit" class="btn btn-sm btn-danger">Hide</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-sm" style="color:var(--text-3)">Nothing to moderate.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $reviews->links() }}</div>
    </div>
</x-app-layout>
