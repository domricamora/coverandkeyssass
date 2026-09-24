<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Commissions</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.payouts.index') }}" class="btn btn-dark btn-sm">Payouts</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">Overview</a>
        </div>
    </div>

    <div class="stat-grid stat-grid--3">
        <div class="stat card"><p class="stat__label">Gross online sales</p><p class="stat__value">PHP {{ number_format((float) $totals->gross, 2) }}</p></div>
        <div class="stat card"><p class="stat__label">Platform revenue</p><p class="stat__value">PHP {{ number_format((float) $totals->fee, 2) }}</p></div>
        <div class="stat card"><p class="stat__label">Host earnings</p><p class="stat__value">PHP {{ number_format((float) $totals->host, 2) }}</p></div>
    </div>

    <form method="POST" action="{{ route('admin.commissions.store') }}" class="card p-6 mb-6 flex flex-wrap gap-3 items-end" style="max-width:820px">
        @csrf
        <input type="hidden" name="kind" value="global">
        <div>
            <label for="g_rate" class="form-label">Global rate (%)</label>
            <input id="g_rate" type="number" step="0.01" min="0" max="100" name="rate" value="{{ $global?->rate ?? $defaultRate }}" required class="form-input" />
        </div>
        <button type="submit" class="btn btn-primary">Save global rate</button>
        <p class="text-sm" style="color:var(--text-3)">{{ $global ? 'Set by admin.' : 'Using the default ('.$defaultRate.'%) until saved.' }}</p>
    </form>

    <form method="POST" action="{{ route('admin.commissions.store') }}" class="card p-6 mb-6 space-y-3" style="max-width:820px">
        @csrf
        <h2 class="text-lg">Add listing or promotional rate</h2>
        <div class="grid grid-cols-4 gap-4">
            <div>
                <label for="c_kind" class="form-label">Kind</label>
                <select id="c_kind" name="kind" class="form-input">
                    <option value="listing">Listing rate</option>
                    <option value="promotional">Promotional (dated)</option>
                </select>
            </div>
            <div>
                <label for="c_rate" class="form-label">Rate (%)</label>
                <input id="c_rate" type="number" step="0.01" min="0" max="100" name="rate" required class="form-input" />
            </div>
            <div>
                <label for="c_type" class="form-label">Listing type</label>
                <select id="c_type" name="listing_type" class="form-input">
                    <option value="property">Property</option>
                    <option value="restaurant">Restaurant</option>
                </select>
            </div>
            <div>
                <label for="c_slug" class="form-label">Listing slug</label>
                <input id="c_slug" name="listing_slug" value="{{ old('listing_slug') }}" class="form-input" placeholder="Blank = all (promo only)" />
            </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="c_name" class="form-label">Name</label>
                <input id="c_name" name="name" value="{{ old('name') }}" class="form-input" />
            </div>
            <div>
                <label for="c_start" class="form-label">Starts (promo)</label>
                <input id="c_start" type="date" name="starts_on" value="{{ old('starts_on') }}" class="form-input" />
            </div>
            <div>
                <label for="c_end" class="form-label">Ends (promo)</label>
                <input id="c_end" type="date" name="ends_on" value="{{ old('ends_on') }}" class="form-input" />
            </div>
        </div>
        @foreach (['rate', 'listing_slug', 'starts_on', 'ends_on'] as $field)
            <x-input-error :messages="$errors->get($field)" />
        @endforeach
        <div class="flex justify-end"><button type="submit" class="btn btn-primary">Add rate</button></div>
    </form>

    <div class="table-wrap card">
        <table class="table">
            <thead><tr><th>Kind</th><th>Applies to</th><th>Rate</th><th>Window</th><th></th></tr></thead>
            <tbody>
                @forelse ($rates as $rate)
                    <tr>
                        <td>{{ ucfirst($rate->kind) }}@if ($rate->name)<br><small style="color:var(--text-3)">{{ $rate->name }}</small>@endif</td>
                        <td>{{ $rate->rateable ? ucfirst($rate->rateable_type).': '.$rate->rateable->name : 'All listings' }}</td>
                        <td>{{ (float) $rate->rate }}%</td>
                        <td>{{ $rate->starts_on?->format('M j, Y') ?? '—' }} – {{ $rate->ends_on?->format('M j, Y') ?? '—' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.commissions.destroy', $rate->id) }}" onsubmit="return confirm('Remove this rate?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">Only the global rate applies.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
