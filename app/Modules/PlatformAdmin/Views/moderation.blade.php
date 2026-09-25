<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Reported content', 'sub' => 'Listings reported by guests. Reported reviews are in Reviews moderation.'])

    <div class="flex flex-wrap gap-2 mt-4">
        @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label)
            <a href="{{ route('admin.moderation.index', ['status' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('admin.reviews.index') }}" class="btn btn-sm btn-ghost">Reported reviews ({{ $flaggedReviews }})</a>
    </div>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">Listing</th><th scope="col">Reason</th><th scope="col">Reported by</th><th scope="col">{{ $status === 'open' ? 'Action' : 'Outcome' }}</th></tr></thead>
            <tbody>
                @forelse ($reports as $report)
                    @php($listing = $report->reportable)
                    <tr>
                        <td>
                            <strong>{{ $listing?->name ?? 'Deleted listing' }}</strong>
                            <br><small>{{ $report->reportable_type }} · {{ $listing?->status }}</small>
                        </td>
                        <td>{{ \App\Modules\PlatformAdmin\Models\ContentReport::REASONS[$report->reason] ?? $report->reason }}@if ($report->details)<br><small>{{ $report->details }}</small>@endif</td>
                        <td>{{ $report->user?->name }}<br><small>{{ $report->created_at->diffForHumans() }}</small></td>
                        <td>
                            @if ($report->status === 'open')
                                <form method="POST" action="{{ route('admin.moderation.resolve', $report) }}" class="flex flex-wrap gap-1">
                                    @csrf
                                    <input name="note" class="form-input" placeholder="Note (needed to suspend)" aria-label="Note" style="max-width:190px" />
                                    <button name="action" value="suspend" class="btn btn-sm btn-dark" type="submit">Suspend listing</button>
                                    <button name="action" value="resolve" class="btn btn-sm btn-ghost" type="submit">Resolved</button>
                                    <button name="action" value="dismiss" class="btn btn-sm btn-ghost" type="submit">Dismiss</button>
                                </form>
                            @else
                                {{ ucfirst($report->status) }} {{ $report->resolved_at?->format('M j') }}{{ $report->resolution_note ? ' — '.$report->resolution_note : '' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="color:var(--text-3)">Nothing to review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $reports->links() }}
</x-app-layout>
