@php($money = fn ($v) => '₱'.number_format((float) $v, 2))
<x-public-layout title="Your cart" :show-search="false">
    <div class="container section" style="max-width:860px;">
        <div class="section-head">
            <span class="eyebrow">Food order</span>
            <h1>Your cart</h1>
            @if ($listing)<p class="muted">From <a href="{{ route('marketplace.restaurants.show', $listing->slug) }}">{{ $listing->name }}</a></p>@endif
        </div>

        @if ($error)<p role="alert" class="card" style="padding:12px;color:var(--danger, #b91c1c);">{{ $error }}</p>@endif
        @foreach (['cart', 'fulfillment', 'payment_method', 'delivery_address', 'delivery_zone_id', 'booking_id', 'room_id', 'room_stay', 'customer_phone', 'promo_code', 'modifiers'] as $field)
            @error($field)<p role="alert" class="card" style="padding:12px;color:var(--danger, #b91c1c);">{{ $message }}</p>@enderror
        @endforeach

        @if (! $listing || $cart['lines'] === [])
            <p class="muted">Your cart is empty. <a href="{{ route('marketplace.restaurants.index') }}">Browse restaurants</a>.</p>
        @elseif ($quote)
            <div class="card" style="padding:16px;">
                @foreach ($quote['lines'] as $i => $line)
                    <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                        <div>
                            <strong>{{ $line['name'] }}</strong> · {{ $money($line['unit_price']) }}
                            @if ($line['modifiers'])<p class="muted" style="margin:2px 0 0;">{{ collect($line['modifiers'])->pluck('name')->implode(', ') }}</p>@endif
                            @if ($line['notes'])<p class="muted" style="margin:2px 0 0;">“{{ $line['notes'] }}”</p>@endif
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <form method="POST" action="{{ route('cart.update', $keys[$i]) }}" style="display:flex;gap:6px;">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="quantity" min="0" max="50" value="{{ $line['quantity'] }}" class="form-input" style="width:70px" aria-label="Quantity of {{ $line['name'] }}" />
                                <button class="btn btn-sm btn-ghost" type="submit">Update</button>
                            </form>
                            <strong style="min-width:90px;text-align:right;">{{ $money($line['line_total']) }}</strong>
                        </div>
                    </div>
                @endforeach

                <div class="price-breakdown" style="margin-top:12px;">
                    <p><span>Subtotal</span> <strong>{{ $money($quote['subtotal']) }}</strong></p>
                    @if ($quote['discount'] > 0)<p><span>Discount ({{ $quote['promotion']->code }})</span> <strong>−{{ $money($quote['discount']) }}</strong></p>@endif
                    <p><span>Tax ({{ (float) $listing->tax_rate }}% {{ $listing->tax_inclusive ? 'included' : 'added' }})</span> <strong>{{ $money($quote['tax']) }}</strong></p>
                    <p><span>Total</span> <strong>{{ $money($quote['total']) }}</strong></p>
                </div>

                <form method="GET" action="{{ route('cart.show') }}" style="display:flex;gap:8px;margin-top:8px;">
                    <input type="text" name="promo_code" value="{{ request('promo_code') }}" class="form-input" placeholder="Promo code" aria-label="Promo code" />
                    <button class="btn btn-ghost" type="submit">Apply</button>
                </form>
            </div>

            @auth
                <form method="POST" action="{{ route('cart.checkout') }}" class="card" style="padding:16px;margin-top:16px;display:grid;gap:10px;" x-data="{ mode: '{{ old('fulfillment', 'pickup') }}' }">
                    @csrf
                    <input type="hidden" name="promo_code" value="{{ request('promo_code') }}" />
                    <h2 style="margin:0;">Checkout</h2>
                    <fieldset style="display:flex;gap:16px;border:0;padding:0;margin:0;">
                        <legend class="muted">How do you want it?</legend>
                        <label><input type="radio" name="fulfillment" value="pickup" x-model="mode" /> Pickup</label>
                        @if ($listing->delivery_enabled && $zones->isNotEmpty())
                            <label><input type="radio" name="fulfillment" value="delivery" x-model="mode" /> Delivery</label>
                        @endif
                        @if ($stays->isNotEmpty())
                            <label><input type="radio" name="fulfillment" value="room_service" x-model="mode" /> Room service</label>
                        @endif
                    </fieldset>
                    @if ($stays->isNotEmpty())
                        <select name="room_stay" class="form-input" aria-label="Deliver to room" x-show="mode === 'room_service'">
                            @foreach ($stays as $stay)
                                @foreach ($stay->rooms as $bookingRoom)
                                    <option value="{{ $stay->id }}:{{ $bookingRoom->room_id }}">Room {{ $bookingRoom->room?->label() }} · {{ $stay->property->name }} ({{ $stay->reference }})</option>
                                @endforeach
                            @endforeach
                        </select>
                    @endif
                    <div x-show="mode === 'delivery'" style="display:grid;gap:8px;"
                         x-data="{ lat: '{{ old('delivery_lat') }}', lng: '{{ old('delivery_lng') }}', locating: false,
                                   locate() { this.locating = true; navigator.geolocation?.getCurrentPosition(p => { this.lat = p.coords.latitude.toFixed(7); this.lng = p.coords.longitude.toFixed(7); this.locating = false }, () => this.locating = false) } }">
                        <select name="delivery_zone_id" class="form-input" aria-label="Delivery area">
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}" @selected((int) old('delivery_zone_id') === $zone->id)>{{ $zone->name }} — {{ $zone->termsLabel() }}</option>
                            @endforeach
                        </select>
                        <textarea name="delivery_address" rows="2" class="form-input" placeholder="Delivery address / landmark" aria-label="Delivery address">{{ old('delivery_address') }}</textarea>
                        <input type="hidden" name="delivery_lat" :value="lat" />
                        <input type="hidden" name="delivery_lng" :value="lng" />
                        <button type="button" class="btn btn-sm btn-ghost" @click="locate()" x-text="lat ? 'Location shared ✓' : (locating ? 'Locating…' : 'Share my location (needed for radius zones)')"></button>
                        <p class="muted" style="margin:0;">The delivery fee is added at checkout from the area you choose.</p>
                    </div>
                    <label class="muted" for="scheduled_for">Schedule for later (optional)</label>
                    <input id="scheduled_for" type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for') }}" class="form-input" min="{{ now()->addMinutes($listing->prep_minutes)->format('Y-m-d\TH:i') }}" />
                    @error('scheduled_for')<p role="alert" style="color:var(--danger, #b91c1c);margin:0;">{{ $message }}</p>@enderror
                    <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" required class="form-input" placeholder="Mobile number" aria-label="Mobile number" />
                    <textarea name="notes" rows="2" class="form-input" placeholder="Notes for the restaurant (optional)" aria-label="Notes">{{ old('notes') }}</textarea>
                    <fieldset style="display:flex;gap:16px;border:0;padding:0;margin:0;">
                        <legend class="muted">Payment</legend>
                        @if ($onlinePayments)
                            <label><input type="radio" name="payment_method" value="online" checked /> Pay now (card, GCash, Maya)</label>
                        @endif
                        <label><input type="radio" name="payment_method" value="cash" @checked(! $onlinePayments) /> Cash on pickup / delivery</label>
                        @if ($stays->isNotEmpty())
                            <label x-show="mode === 'room_service'"><input type="radio" name="payment_method" value="room_charge" /> Charge to my room</label>
                        @endif
                    </fieldset>
                    <button class="btn btn-primary btn-block" type="submit">Place order · {{ $money($quote['total']) }}</button>
                </form>
            @else
                <a class="btn btn-primary btn-block" style="margin-top:16px;" href="{{ route('login') }}">Sign in to check out</a>
            @endauth
        @endif
    </div>
</x-public-layout>
