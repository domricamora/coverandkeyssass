<x-app-layout>
    @include('platform-admin::partials.head', ['title' => ucfirst($kind), 'sub' => 'Approve pending listings, suspend or reinstate. Every change is audited.'])

    <form method="GET" class="admin-search mt-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name…" class="form-input" aria-label="Search {{ $kind }}" />
        <select name="status" class="form-input" aria-label="Status" style="max-width:160px">
            <option value="">Any status</option>
            @foreach (['draft', 'pending', 'published', 'suspended'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
    </form>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">Listing</th><th scope="col">Business</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
            <tbody>
                @forelse ($listings as $listing)
                    <tr>
                        <td>
                            <strong>{{ $listing->name }}</strong>
                            @if ($listing->status === 'published')
                                <a href="{{ $kind === 'properties' ? route('marketplace.properties.show', $listing->slug) : route('marketplace.restaurants.show', $listing->slug) }}" class="text-xs">view</a>
                            @endif
                        </td>
                        <td>{{ $listing->tenant?->name }}</td>
                        <td><span class="badge {{ ['published' => 'badge-green', 'pending' => 'badge-amber', 'suspended' => 'badge-amber'][$listing->status] ?? '' }}">{{ $listing->status }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.listings.status', [$kind, $listing->id]) }}" class="flex flex-wrap gap-1">
                                @csrf
                                @if ($listing->status !== 'published')
                                    <button name="status" value="published" class="btn btn-sm btn-dark" type="submit">{{ $listing->status === 'pending' ? 'Approve' : 'Publish' }}</button>
                                @endif
                                @if ($listing->status !== 'suspended')
                                    <input name="reason" class="form-input" placeholder="Reason to suspend" aria-label="Reason to suspend {{ $listing->name }}" style="max-width:180px" />
                                    <button name="status" value="suspended" class="btn btn-sm btn-ghost" type="submit">Suspend</button>
                                @endif
                                @if ($listing->status === 'pending')
                                    <button name="status" value="draft" class="btn btn-sm btn-ghost" type="submit">Send back</button>
                                @endif
                            </form>
                            <details class="mt-2">
                                <summary class="text-xs" style="cursor:pointer">
                                    Placement
                                    @if ($listing->isSponsored()) · <strong>sponsored</strong> @endif
                                    @if ($listing->isFeaturedNow()) · <strong>featured</strong> @endif
                                    @if ($listing->ranking_boost) · boost {{ $listing->ranking_boost }} @endif
                                    @if ($listing->isVerified()) · <strong>verified</strong> @endif
                                </summary>
                                <form method="POST" action="{{ route('admin.listings.placement', [$kind, $listing->id]) }}" class="mt-2" style="display:grid;gap:6px;max-width:320px">
                                    @csrf
                                    <label><input type="checkbox" name="is_featured" value="1" @checked($listing->is_featured) /> Featured</label>
                                    <label class="text-xs">Featured until (empty = no end) <input type="date" name="featured_until" value="{{ $listing->featured_until?->toDateString() }}" class="form-input" /></label>
                                    <label class="text-xs">Sponsored until (paid placement, labelled) <input type="date" name="sponsored_until" value="{{ $listing->sponsored_until?->toDateString() }}" class="form-input" /></label>
                                    <label class="text-xs">Ranking boost (−50 to 50) <input type="number" name="ranking_boost" min="-50" max="50" value="{{ $listing->ranking_boost }}" class="form-input" /></label>
                                    <label><input type="checkbox" name="verified" value="1" @checked($listing->isVerified()) /> Verified (documents checked)</label>
                                    <button class="btn btn-sm btn-dark" type="submit" style="justify-self:start">Save placement</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="color:var(--text-3)">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $listings->links() }}
</x-app-layout>
