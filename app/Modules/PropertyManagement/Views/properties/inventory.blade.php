<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Inventory — {{ $property->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Room types, physical rooms, rate periods and availability blocks.</p>
        </div>
        <a href="{{ route('properties.show', $property) }}" class="btn btn-ghost">Back to property</a>
    </div>

    <div class="card mt-6 p-6" style="max-width:820px">
        <h2 class="text-lg">Add room type</h2>
        <form method="POST" action="{{ route('properties.room-types.store', $property) }}" class="mt-3 space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="rt_name" class="form-label">Name</label>
                    <input id="rt_name" name="name" type="text" value="{{ old('name') }}" required class="form-input" placeholder="Deluxe Room" />
                </div>
                <div>
                    <label for="rt_guests" class="form-label">Max guests</label>
                    <input id="rt_guests" name="max_guests" type="number" min="1" value="{{ old('max_guests', 2) }}" required class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="rt_price" class="form-label">Nightly rate</label>
                    <input id="rt_price" name="base_price" type="number" step="0.01" min="0" value="{{ old('base_price') }}" required class="form-input" />
                </div>
                <div>
                    <label for="rt_weekend" class="form-label">Weekend rate</label>
                    <input id="rt_weekend" name="weekend_price" type="number" step="0.01" min="0" value="{{ old('weekend_price') }}" class="form-input" />
                </div>
                <div>
                    <label for="rt_min_stay" class="form-label">Min stay (nights)</label>
                    <input id="rt_min_stay" name="min_stay_nights" type="number" min="1" value="{{ old('min_stay_nights', 1) }}" class="form-input" />
                </div>
                <div>
                    <label for="rt_status" class="form-label">Status</label>
                    <select id="rt_status" name="status" class="form-input">
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">Add room type</button>
            </div>
        </form>
        <x-input-error :messages="$errors->get('name')" />
        <x-input-error :messages="$errors->get('base_price')" />
    </div>

    @forelse ($roomTypes as $type)
        <div class="card mt-6 p-6" style="max-width:820px">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-lg">{{ $type->name }}</h2>
                    <p class="text-sm mt-1" style="color:var(--text-3)">
                        {{ $type->max_guests }} guests · {{ $type->priceLabel() }}/night · min {{ $type->min_stay_nights }} night(s)
                        · {{ $type->rooms_count }} room(s) · {{ ucfirst($type->status) }}
                    </p>
                </div>
                <form method="POST" action="{{ route('properties.room-types.destroy', [$property, $type]) }}" onsubmit="return confirm('Remove this room type?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Remove type</button>
                </form>
            </div>

            <details class="mt-4" open>
                <summary class="text-sm font-semibold cursor-pointer">Rooms ({{ $type->rooms_count }})</summary>
                <table class="table mt-2">
                    <thead>
                        <tr><th>Room</th><th>Floor</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($type->rooms as $room)
                            <tr>
                                <td><strong style="color:var(--text)">{{ $room->label() }}</strong></td>
                                <td style="color:var(--text-2)">{{ $room->floor ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('properties.rooms.update', [$property, $type, $room]) }}" class="flex gap-2 items-center">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="form-input" style="max-width:150px">
                                            @foreach (\App\Modules\PropertyManagement\Models\Room::statuses() as $status)
                                                <option value="{{ $status }}" @selected($room->status === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-dark">Set</button>
                                    </form>
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('properties.rooms.destroy', [$property, $type, $room]) }}" onsubmit="return confirm('Remove this room from inventory?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-center text-sm" style="color:var(--text-3)">No rooms yet for this type.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <form method="POST" action="{{ route('properties.rooms.store', [$property, $type]) }}" class="mt-3 flex gap-2 items-end">
                    @csrf
                    <div>
                        <label class="form-label" for="room_number_{{ $type->getKey() }}">Room number</label>
                        <input id="room_number_{{ $type->getKey() }}" name="room_number" type="text" required class="form-input" style="max-width:140px" placeholder="101" />
                    </div>
                    <div>
                        <label class="form-label" for="room_floor_{{ $type->getKey() }}">Floor</label>
                        <input id="room_floor_{{ $type->getKey() }}" name="floor" type="number" class="form-input" style="max-width:100px" />
                    </div>
                    <button type="submit" class="btn btn-primary">Add room</button>
                </form>
                <x-input-error :messages="$errors->get('room_number')" />
            </details>

            <details class="mt-3">
                <summary class="text-sm font-semibold cursor-pointer">Rates ({{ $type->rate_periods_count }})</summary>
                <table class="table mt-2">
                    <thead>
                        <tr><th>Range</th><th>Nightly</th><th>Weekend</th><th>Min stay</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($type->ratePeriods as $rate)
                            <tr>
                                <td style="color:var(--text-2)">{{ $rate->rangeLabel() }}</td>
                                <td style="color:var(--text-2)">{{ $type->currency }} {{ number_format((float) $rate->nightly_price, 0) }}</td>
                                <td style="color:var(--text-2)">{{ $rate->weekend_nightly_price ? number_format((float) $rate->weekend_nightly_price, 0) : '—' }}</td>
                                <td style="color:var(--text-2)">{{ $rate->min_stay_nights }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('properties.rates.destroy', [$property, $type, $rate]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-sm" style="color:var(--text-3)">No rate periods — the base rate applies every night.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <form method="POST" action="{{ route('properties.rates.store', [$property, $type]) }}" class="mt-3 grid grid-cols-5 gap-2 items-end">
                    @csrf
                    <div>
                        <label class="form-label" for="rate_name_{{ $type->getKey() }}">Label</label>
                        <input id="rate_name_{{ $type->getKey() }}" name="name" type="text" class="form-input" placeholder="High season" />
                    </div>
                    <div>
                        <label class="form-label" for="rate_start_{{ $type->getKey() }}">From</label>
                        <input id="rate_start_{{ $type->getKey() }}" name="start_date" type="date" required class="form-input" />
                    </div>
                    <div>
                        <label class="form-label" for="rate_end_{{ $type->getKey() }}">To</label>
                        <input id="rate_end_{{ $type->getKey() }}" name="end_date" type="date" required class="form-input" />
                    </div>
                    <div>
                        <label class="form-label" for="rate_nightly_{{ $type->getKey() }}">Nightly</label>
                        <input id="rate_nightly_{{ $type->getKey() }}" name="nightly_price" type="number" step="0.01" min="0" required class="form-input" />
                    </div>
                    <button type="submit" class="btn btn-primary">Add rate</button>
                </form>
                <x-input-error :messages="$errors->get('start_date')" />
            </details>
        </div>
    @empty
        <div class="card mt-6 p-6" style="max-width:820px">
            <p class="text-sm" style="color:var(--text-3)">No room types yet — add the first one above.</p>
        </div>
    @endforelse

    <div class="card mt-6 p-6 mb-10" style="max-width:820px">
        <h2 class="text-lg">Availability blocks</h2>
        <p class="text-sm" style="color:var(--text-3)">Take a room type — or a single room — off the market for an inclusive date range (maintenance, owner stays, private events).</p>

        <table class="table mt-3">
            <thead>
                <tr><th>Target</th><th>Range</th><th>Reason</th><th>Note</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($blocks as $block)
                    <tr>
                        <td style="color:var(--text-2)">
                            {{ $block->roomType?->name ?? '—' }}@if ($block->room) · room {{ $block->room->room_number }} @endif
                        </td>
                        <td style="color:var(--text-2)">{{ $block->rangeLabel() }}</td>
                        <td><span class="badge badge-amber">{{ ucfirst(str_replace('_', ' ', $block->reason)) }}</span></td>
                        <td style="color:var(--text-3)">{{ $block->note ?? '—' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('properties.availability.destroy', [$property, $block]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-sm" style="color:var(--text-3)">Nothing blocked — all sellable rooms are open.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($roomTypes->isNotEmpty())
            <form method="POST" action="{{ route('properties.availability.store', $property) }}" class="mt-4 grid grid-cols-5 gap-2 items-end">
                @csrf
                <div>
                    <label class="form-label" for="block_type">Room type</label>
                    <select id="block_type" name="room_type_id" class="form-input">
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->getKey() }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="block_room">Room (optional)</label>
                    <select id="block_room" name="room_id" class="form-input">
                        <option value="">Whole type</option>
                        @foreach ($roomTypes->flatMap->rooms as $room)
                            <option value="{{ $room->getKey() }}">{{ $room->label() }} ({{ $type->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="block_start">From</label>
                    <input id="block_start" name="start_date" type="date" required class="form-input" />
                </div>
                <div>
                    <label class="form-label" for="block_end">To</label>
                    <input id="block_end" name="end_date" type="date" required class="form-input" />
                </div>
                <button type="submit" class="btn btn-primary">Add block</button>
                <div class="col-span-2">
                    <label class="form-label" for="block_reason">Reason</label>
                    <select id="block_reason" name="reason" class="form-input">
                        @foreach (\App\Modules\PropertyManagement\Models\AvailabilityBlock::reasons() as $reason)
                            <option value="{{ $reason }}">{{ ucfirst(str_replace('_', ' ', $reason)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-3">
                    <label class="form-label" for="block_note">Note</label>
                    <input id="block_note" name="note" type="text" class="form-input" placeholder="Poolside renovation" />
                </div>
            </form>
            <x-input-error :messages="$errors->get('start_date')" />
        @endif
    </div>
</x-app-layout>
