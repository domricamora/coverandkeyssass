<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>New booking</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Manual reservation, hold or walk-in. Availability is re-checked under lock when you save.</p>
        </div>
        <a href="{{ route('bookings.index') }}" class="btn btn-ghost">Back to bookings</a>
    </div>

    <form method="GET" class="card p-4 mb-4 flex flex-wrap gap-3 items-end" style="max-width:820px">
        <div>
            <label for="c_property" class="form-label">Property</label>
            <select id="c_property" name="property" class="form-input" required>
                <option value="">Choose…</option>
                @foreach ($properties as $option)
                    <option value="{{ $option->slug }}" @selected($property?->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="c_in" class="form-label">Check-in</label>
            <input id="c_in" type="date" name="check_in" value="{{ request('check_in') }}" class="form-input" />
        </div>
        <div>
            <label for="c_out" class="form-label">Check-out</label>
            <input id="c_out" type="date" name="check_out" value="{{ request('check_out') }}" class="form-input" />
        </div>
        <button class="btn btn-dark" type="submit">Check availability</button>
    </form>

    @if ($property)
        <form method="POST" action="{{ route('bookings.store') }}" class="card p-6 space-y-4" style="max-width:820px">
            @csrf
            <input type="hidden" name="property" value="{{ $property->slug }}">

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="b_source" class="form-label">Type</label>
                    <select id="b_source" name="source" class="form-input">
                        <option value="manual" @selected(old('source') === 'manual')>Reservation</option>
                        <option value="walk_in" @selected(old('source') === 'walk_in')>Walk-in (checks in now)</option>
                    </select>
                </div>
                <div>
                    <label for="b_in" class="form-label">Check-in</label>
                    <input id="b_in" type="date" name="check_in" value="{{ old('check_in', request('check_in')) }}" required class="form-input" />
                </div>
                <div>
                    <label for="b_out" class="form-label">Check-out</label>
                    <input id="b_out" type="date" name="check_out" value="{{ old('check_out', request('check_out')) }}" required class="form-input" />
                </div>
            </div>

            <div>
                <p class="form-label">Rooms</p>
                <table class="table">
                    <thead><tr><th>Room type</th><th>Sleeps</th><th>Rate</th><th>Free</th><th>Quantity</th></tr></thead>
                    <tbody>
                        @forelse ($roomTypes as $i => $type)
                            <tr>
                                <td>{{ $type->name }}<input type="hidden" name="rooms[{{ $i }}][room_type_id]" value="{{ $type->id }}"></td>
                                <td>{{ $type->max_guests }}</td>
                                <td>{{ $type->priceLabel() }}</td>
                                <td>{{ $available[$type->id] ?? '—' }}</td>
                                <td><input type="number" min="0" max="50" name="rooms[{{ $i }}][quantity]" value="{{ old('rooms.'.$i.'.quantity', 0) }}" class="form-input" style="max-width:90px" aria-label="Quantity of {{ $type->name }}"></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-sm" style="color:var(--text-3)">This property has no active room types yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <x-input-error :messages="$errors->get('rooms')" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="b_name" class="form-label">Guest name</label>
                    <input id="b_name" name="guest_name" value="{{ old('guest_name') }}" required class="form-input" />
                </div>
                <div>
                    <label for="b_email" class="form-label">Email</label>
                    <input id="b_email" type="email" name="guest_email" value="{{ old('guest_email') }}" class="form-input" />
                </div>
                <div>
                    <label for="b_phone" class="form-label">Phone</label>
                    <input id="b_phone" name="guest_phone" value="{{ old('guest_phone') }}" class="form-input" />
                </div>
            </div>

            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="b_adults" class="form-label">Adults</label>
                    <input id="b_adults" type="number" min="1" name="adults" value="{{ old('adults', 1) }}" class="form-input" />
                </div>
                <div>
                    <label for="b_children" class="form-label">Children</label>
                    <input id="b_children" type="number" min="0" name="children" value="{{ old('children', 0) }}" class="form-input" />
                </div>
                <div>
                    <label for="b_group" class="form-label">Group name</label>
                    <input id="b_group" name="group_name" value="{{ old('group_name') }}" class="form-input" placeholder="Optional" />
                </div>
                <div>
                    <label for="b_hold" class="form-label">Hold only (hours)</label>
                    <input id="b_hold" type="number" min="1" max="168" name="hold_hours" value="{{ old('hold_hours') }}" class="form-input" placeholder="Confirm now" />
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="b_promo" class="form-label">Promo code</label>
                    <input id="b_promo" name="promo_code" value="{{ old('promo_code') }}" class="form-input" />
                </div>
                <div class="col-span-2">
                    <label for="b_notes" class="form-label">Special requests</label>
                    <input id="b_notes" name="special_requests" value="{{ old('special_requests') }}" class="form-input" />
                </div>
            </div>

            @foreach (['check_in', 'check_out', 'adults', 'promo_code', 'guest_name'] as $field)
                <x-input-error :messages="$errors->get($field)" />
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">Create booking</button>
            </div>
        </form>
    @endif
</x-app-layout>
