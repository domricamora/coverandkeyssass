@php($canManage = auth()->user()->hasPermissionTo('tables.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Tables — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Dining areas and the tables guests are seated at.</p>
        </div>
        <a href="{{ route('restaurants.show', $restaurant) }}" class="btn btn-ghost">Back to restaurant</a>
    </div>

    <x-input-error :messages="$errors->get('name')" />
    <x-input-error :messages="$errors->get('label')" />
    <x-input-error :messages="$errors->get('seats')" />

    <div class="grid grid-cols-2 gap-4 mt-6" style="max-width:1100px">
        <div class="card p-6">
            <h2 class="text-lg">Dining areas</h2>
            <ul class="mt-3 text-sm space-y-2" style="color:var(--text-2)">
                @forelse ($areas as $area)
                    <li class="flex justify-between items-center">
                        <span><strong>{{ $area->name }}</strong> · {{ $tables->where('dining_area_id', $area->id)->count() }} table(s)</span>
                        @if ($canManage)
                            <form method="POST" action="{{ route('restaurants.areas.destroy', [$restaurant, $area->id]) }}" onsubmit="return confirm('Remove this area? Its tables stay, unassigned.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li style="color:var(--text-3)">No areas yet.</li>
                @endforelse
            </ul>
            @if ($canManage)
                <form method="POST" action="{{ route('restaurants.areas.store', $restaurant) }}" class="flex gap-2 mt-4">
                    @csrf
                    <input name="name" type="text" required class="form-input" placeholder="Terrace" aria-label="Area name" />
                    <button type="submit" class="btn btn-primary">Add area</button>
                </form>
            @endif
        </div>

        @if ($canManage)
            <form method="POST" action="{{ route('restaurants.tables.store', $restaurant) }}" class="card p-6 space-y-3">
                @csrf
                <h2 class="text-lg">Add table</h2>
                <div class="grid grid-cols-3 gap-3">
                    <input name="label" type="text" required class="form-input" placeholder="T1" aria-label="Table label" />
                    <input name="seats" type="number" min="1" value="4" required class="form-input" aria-label="Seats" />
                    <select name="dining_area_id" class="form-input" aria-label="Dining area">
                        <option value="">No area</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="status" value="active" />
                <div class="flex justify-end"><button type="submit" class="btn btn-primary">Add table</button></div>
            </form>
        @endif
    </div>

    <div class="table-wrap card mt-6" style="max-width:1100px">
        <table class="table">
            <thead><tr><th>Table</th><th>Seats</th><th>Area</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                @forelse ($tables as $table)
                    <tr>
                        <td><strong style="color:var(--text)">{{ $table->label }}</strong></td>
                        <td style="color:var(--text-2)">{{ $table->seats }}</td>
                        <td style="color:var(--text-2)">{{ $table->area?->name ?? '—' }}</td>
                        <td><span class="badge {{ $table->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($table->status) }}</span></td>
                        <td class="text-right">
                            @if ($canManage)
                                <form method="POST" action="{{ route('restaurants.tables.update', [$restaurant, $table->id]) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="label" value="{{ $table->label }}" />
                                    <input type="hidden" name="seats" value="{{ $table->seats }}" />
                                    <input type="hidden" name="dining_area_id" value="{{ $table->dining_area_id }}" />
                                    <input type="hidden" name="status" value="{{ $table->status === 'active' ? 'inactive' : 'active' }}" />
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ $table->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                <form method="POST" action="{{ route('restaurants.tables.destroy', [$restaurant, $table->id]) }}" class="inline" onsubmit="return confirm('Remove this table?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No tables yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
