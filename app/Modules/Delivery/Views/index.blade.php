<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Delivery — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                Delivery is {{ $restaurant->delivery_enabled ? 'on' : 'off' }} ({{ $zones->where('is_active', true)->count() }} active zone(s)).
                @unless ($restaurant->delivery_enabled) Turn it on in the <a href="{{ route('restaurants.edit', $restaurant) }}">profile</a>. @endunless
            </p>
        </div>
        <a href="{{ route('restaurants.orders.index', $restaurant) }}" class="btn btn-ghost">Orders</a>
    </div>

    @foreach (['name', 'fee', 'eta_minutes', 'radius_km', 'prep_minutes', 'latitude', 'longitude'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="card p-6" style="grid-column:span 2">
            <h2 class="text-lg">Zones</h2>
            <table class="table mt-2">
                <thead><tr><th>Zone</th><th>Terms</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($zones as $zone)
                        <tr>
                            <td><strong style="color:var(--text)">{{ $zone->name }}</strong> @unless ($zone->is_active)<span class="badge badge-gray">Paused</span>@endunless</td>
                            <td class="text-sm" style="color:var(--text-2)">{{ $zone->termsLabel() }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('restaurants.delivery.zones.toggle', [$restaurant, $zone->id]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ $zone->is_active ? 'Pause' : 'Reopen' }}</button>
                                </form>
                                <form method="POST" action="{{ route('restaurants.delivery.zones.destroy', [$restaurant, $zone->id]) }}" class="inline" onsubmit="return confirm('Remove this zone?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-sm" style="color:var(--text-3)">No zones yet — customers cannot choose delivery until you add one.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <form method="POST" action="{{ route('restaurants.delivery.zones.store', $restaurant) }}" class="grid grid-cols-3 gap-2 mt-4">
                @csrf
                <input name="name" type="text" required class="form-input" placeholder="Station 1 or Within 3 km" aria-label="Zone name" />
                <input name="fee" type="number" step="0.01" min="0" required class="form-input" placeholder="Fee ₱" aria-label="Fee" />
                <input name="eta_minutes" type="number" min="5" value="30" required class="form-input" aria-label="Ride minutes" title="Ride minutes" />
                <input name="radius_km" type="number" step="0.1" min="0.1" class="form-input" placeholder="Radius km (blank = named area)" aria-label="Radius in km" />
                <input name="min_order" type="number" step="0.01" min="0" class="form-input" placeholder="Minimum order ₱" aria-label="Minimum order" />
                <input name="free_over" type="number" step="0.01" min="0" class="form-input" placeholder="Free over ₱" aria-label="Free delivery over" />
                <div style="grid-column:span 3" class="flex justify-end"><button type="submit" class="btn btn-primary">Add zone</button></div>
            </form>
        </div>

        <div class="space-y-4">
            <form method="POST" action="{{ route('restaurants.delivery.settings', $restaurant) }}" class="card p-6 space-y-2">
                @csrf
                @method('PATCH')
                <h2 class="text-lg">Kitchen & location</h2>
                <label class="form-label" for="prep_minutes">Prep time (minutes)</label>
                <input id="prep_minutes" name="prep_minutes" type="number" min="0" max="240" value="{{ $restaurant->prep_minutes }}" class="form-input" />
                <label class="form-label" for="latitude">Restaurant coordinates (for radius zones)</label>
                <div class="flex gap-2">
                    <input id="latitude" name="latitude" type="number" step="0.0000001" value="{{ $restaurant->latitude }}" class="form-input" placeholder="Latitude" />
                    <input name="longitude" type="number" step="0.0000001" value="{{ $restaurant->longitude }}" class="form-input" placeholder="Longitude" aria-label="Longitude" />
                </div>
                <button type="submit" class="btn btn-primary w-full">Save</button>
            </form>

            <div class="card p-6">
                <h2 class="text-lg">Drivers</h2>
                <ul class="mt-2 text-sm space-y-2" style="color:var(--text-2)">
                    @forelse ($drivers as $driver)
                        <li class="flex justify-between items-center">
                            <span><strong>{{ $driver->name }}</strong> {{ $driver->vehicle ? '· '.$driver->vehicle : '' }} {{ $driver->phone ? '· '.$driver->phone : '' }}
                                @unless ($driver->is_active)<span class="badge badge-gray">Off duty</span>@endunless</span>
                            <form method="POST" action="{{ route('restaurants.delivery.drivers.toggle', [$restaurant, $driver->id]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost">{{ $driver->is_active ? 'Off duty' : 'On duty' }}</button>
                            </form>
                        </li>
                    @empty
                        <li style="color:var(--text-3)">No drivers yet.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('restaurants.delivery.drivers.store', $restaurant) }}" class="mt-3 space-y-2">
                    @csrf
                    <input name="name" type="text" required class="form-input" placeholder="Driver name" aria-label="Driver name" />
                    <input name="phone" type="text" class="form-input" placeholder="Phone" aria-label="Driver phone" />
                    <input name="vehicle" type="text" class="form-input" placeholder="Vehicle (e.g. motorbike)" aria-label="Vehicle" />
                    <button type="submit" class="btn btn-primary w-full">Add driver</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
