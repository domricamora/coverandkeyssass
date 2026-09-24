<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Promotions</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Promo codes guests and staff can apply to a booking.</p>
        </div>
        <a href="{{ route('bookings.index') }}" class="btn btn-ghost">Back to bookings</a>
    </div>

    <form method="POST" action="{{ route('bookings.promotions.store') }}" class="card p-6 mb-6 space-y-3" style="max-width:820px">
        @csrf
        <div class="grid grid-cols-4 gap-4">
            <div>
                <label for="p_code" class="form-label">Code</label>
                <input id="p_code" name="code" value="{{ old('code') }}" required class="form-input" placeholder="SUMMER10" />
            </div>
            <div class="col-span-2">
                <label for="p_name" class="form-label">Name</label>
                <input id="p_name" name="name" value="{{ old('name') }}" required class="form-input" />
            </div>
            <div>
                <label for="p_property" class="form-label">Property</label>
                <select id="p_property" name="property_id" class="form-input">
                    <option value="">All properties</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}">{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="grid grid-cols-6 gap-4">
            <div>
                <label for="p_type" class="form-label">Type</label>
                <select id="p_type" name="type" class="form-input">
                    <option value="percent">% off</option>
                    <option value="fixed">Fixed off</option>
                </select>
            </div>
            <div>
                <label for="p_value" class="form-label">Value</label>
                <input id="p_value" type="number" step="0.01" min="0" name="value" value="{{ old('value') }}" required class="form-input" />
            </div>
            <div>
                <label for="p_start" class="form-label">Starts</label>
                <input id="p_start" type="date" name="starts_on" value="{{ old('starts_on') }}" class="form-input" />
            </div>
            <div>
                <label for="p_end" class="form-label">Ends</label>
                <input id="p_end" type="date" name="ends_on" value="{{ old('ends_on') }}" class="form-input" />
            </div>
            <div>
                <label for="p_min" class="form-label">Min nights</label>
                <input id="p_min" type="number" min="1" name="min_nights" value="{{ old('min_nights', 1) }}" class="form-input" />
            </div>
            <div>
                <label for="p_max" class="form-label">Max uses</label>
                <input id="p_max" type="number" min="1" name="max_uses" value="{{ old('max_uses') }}" class="form-input" />
            </div>
        </div>
        @foreach (['code', 'name', 'value', 'ends_on'] as $field)
            <x-input-error :messages="$errors->get($field)" />
        @endforeach
        <div class="flex justify-end"><button type="submit" class="btn btn-primary">Create promo code</button></div>
    </form>

    <div class="table-wrap card">
        <table class="table">
            <thead><tr><th>Code</th><th>Discount</th><th>Valid</th><th>Used</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($promotions as $promotion)
                    <tr>
                        <td><strong>{{ $promotion->code }}</strong><br><small style="color:var(--text-3)">{{ $promotion->name }}</small></td>
                        <td>{{ $promotion->label() }}</td>
                        <td>{{ $promotion->starts_on?->format('M j, Y') ?? 'Any' }} – {{ $promotion->ends_on?->format('M j, Y') ?? 'Any' }}</td>
                        <td>{{ $promotion->used_count }}{{ $promotion->max_uses ? ' / '.$promotion->max_uses : '' }}</td>
                        <td><span class="badge {{ $promotion->is_active ? 'badge-green' : 'badge-gray' }}">{{ $promotion->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('bookings.promotions.toggle', $promotion->id) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-ghost" type="submit">{{ $promotion->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-3)">No promo codes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
