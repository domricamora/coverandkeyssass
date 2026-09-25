<?php

namespace App\Modules\Booking\Services;

use App\Models\User;
use App\Modules\Booking\Events\BookingTransitioned;
use App\Modules\Booking\Events\BookingTransitioning;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Booking\Notifications\BookingStatusChanged;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\RoomType;
use App\Modules\PropertyManagement\Services\AvailabilityService;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The booking engine (Phase 05). Every reservation and every state change
 * goes through here.
 *
 * Double booking is prevented twice over:
 *  1. reserve() locks the room type's rooms (SELECT … FOR UPDATE) inside the
 *     transaction, so concurrent reservations for the same room type queue
 *     up and each one re-reads free inventory after the previous commits;
 *  2. every occupied room-night is a `room_nights` row with a unique
 *     (room_id, night) index — if anything slips past the lock, the insert
 *     fails and the whole reservation rolls back.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Reserve rooms for [check_in, check_out).
     *
     * @param  array{check_in: string, check_out: string, rooms: list<array{room_type_id: int|string, quantity: int|string}>,
     *               adults?: int, children?: int, guest_name: string, guest_email?: ?string, guest_phone?: ?string,
     *               special_requests?: ?string, group_name?: ?string, promo_code?: ?string, hold_hours?: ?int}  $data
     */
    public function reserve(Property $property, array $data, string $source, ?User $customer = null, ?User $actor = null): Booking
    {
        $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();
        $nights = (int) $checkIn->diffInDays($checkOut, false);

        match (true) {
            $nights < 1 => $this->fail('check_out', 'Check-out must be after check-in.'),
            $source === Booking::SOURCE_WALK_IN && ! $checkIn->isToday() => $this->fail('check_in', 'A walk-in stay starts today.'),
            $checkIn->lessThan(today()) => $this->fail('check_in', 'Check-in cannot be in the past.'),
            default => null,
        };

        $lines = collect($data['rooms'] ?? [])
            ->groupBy(fn ($line) => (int) $line['room_type_id'])
            ->map(fn ($group) => (int) $group->sum('quantity'))
            ->filter(fn (int $quantity) => $quantity > 0);

        if ($lines->isEmpty()) {
            $this->fail('rooms', 'Select at least one room.');
        }

        $types = $property->roomTypes()->active()->whereIn('id', $lines->keys())->get()->keyBy('id');

        if ($types->count() !== $lines->count()) {
            $this->fail('rooms', 'One of the selected room types is not available at this property.');
        }

        $adults = max(1, (int) ($data['adults'] ?? 1));
        $children = max(0, (int) ($data['children'] ?? 0));
        $capacity = $lines->sum(fn (int $quantity, int $typeId) => $types[$typeId]->max_guests * $quantity);

        if ($adults + $children > $capacity) {
            $this->fail('adults', 'The selected rooms sleep at most '.$capacity.' guests.');
        }

        $promotion = $this->promotionFor($property, $data['promo_code'] ?? null, $checkIn, $nights);
        $status = match ($source) {
            Booking::SOURCE_WALK_IN => Booking::CHECKED_IN,
            Booking::SOURCE_MARKETPLACE => Booking::PENDING,
            default => empty($data['hold_hours']) ? Booking::CONFIRMED : Booking::HELD,
        };

        // Outside the transaction so the lock below is the first statement
        // inside it (InnoDB then reads free inventory after waiting on it).
        $this->releaseExpiredHolds($property);

        try {
            $booking = DB::transaction(function () use ($property, $data, $source, $customer, $actor, $checkIn, $checkOut, $lines, $types, $adults, $children, $promotion, $status) {
                $lastNight = $checkOut->subDay();
                $assignments = [];

                foreach ($lines as $typeId => $quantity) {
                    $type = $types[$typeId];

                    // Serialise concurrent reservations of this room type.
                    $type->rooms()->sellable()->lockForUpdate()->pluck('id');

                    $free = $this->availability->freeRoomIds($type, $checkIn, $lastNight);

                    if (count($free) < $quantity) {
                        $this->fail('rooms', count($free) === 0
                            ? $type->name.' is sold out for these dates.'
                            : 'Only '.count($free).' '.$type->name.' room(s) left for these dates.');
                    }

                    $quote = $this->quote($type, $checkIn, $checkOut);

                    foreach (array_slice($free, 0, $quantity) as $roomId) {
                        $assignments[] = [$type, $roomId, $quote];
                    }
                }

                $subtotal = round(array_sum(array_map(fn ($a) => $a[2]['total'], $assignments)), 2);
                $discount = $promotion?->discountOn($subtotal) ?? 0.0;

                $booking = Booking::create([
                    'property_id' => $property->getKey(),
                    'user_id' => $customer?->getKey(),
                    'created_by' => $actor?->getKey(),
                    'promotion_id' => $promotion?->getKey(),
                    'source' => $source,
                    'status' => $status,
                    'group_name' => $data['group_name'] ?? null,
                    'check_in' => $checkIn->toDateString(),
                    'check_out' => $checkOut->toDateString(),
                    'adults' => $adults,
                    'children' => $children,
                    'guest_name' => $data['guest_name'],
                    'guest_email' => $data['guest_email'] ?? $customer?->email,
                    'guest_phone' => $data['guest_phone'] ?? null,
                    'special_requests' => $data['special_requests'] ?? null,
                    'currency' => $types->first()->currency ?: $property->currency,
                    'subtotal' => $subtotal,
                    'discount_total' => $discount,
                    'total' => $subtotal - $discount,
                    'hold_expires_at' => $status === Booking::HELD ? now()->addHours((int) $data['hold_hours']) : null,
                ]);

                $booking->forceFill([
                    'confirmed_at' => in_array($status, [Booking::CONFIRMED, Booking::CHECKED_IN], true) ? now() : null,
                    'checked_in_at' => $status === Booking::CHECKED_IN ? now() : null,
                ])->save();

                foreach ($assignments as [$type, $roomId, $quote]) {
                    $bookingRoom = $booking->rooms()->create([
                        'room_type_id' => $type->getKey(),
                        'room_id' => $roomId,
                        'nightly_rates' => $quote['rates'],
                        'total' => $quote['total'],
                    ]);

                    DB::table('room_nights')->insert(array_map(fn (string $night) => [
                        'booking_room_id' => $bookingRoom->getKey(),
                        'room_id' => $roomId,
                        'night' => $night,
                    ], array_keys($quote['rates'])));
                }

                $promotion?->redeem($booking->reference);

                return $booking;
            });
        } catch (UniqueConstraintViolationException) {
            $this->fail('rooms', 'Those rooms were just booked by someone else. Please pick again.');
        }

        $this->audit->log('booking.created', $booking, null, [
            'reference' => $booking->reference,
            'source' => $source,
            'status' => $status,
            'total' => $booking->total,
        ]);

        return $booking;
    }

    /**
     * Price every night of [check_in, check_out) for a room type: an active
     * rate period wins over the room type defaults; Friday and Saturday
     * nights use the weekend price when one is set.
     *
     * @return array{rates: array<string, string>, total: float}
     */
    public function quote(RoomType $type, CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $periods = $type->ratePeriods()
            ->overlapping($checkIn->toDateString(), $checkOut->subDay()->toDateString())
            ->get();

        $rates = [];
        $minStay = max(1, (int) $type->min_stay_nights);

        for ($night = $checkIn; $night->lessThan($checkOut); $night = $night->addDay()) {
            $date = $night->toDateString();
            $period = $periods->first(fn ($p) => $p->start_date->toDateString() <= $date && $p->end_date->toDateString() >= $date);
            [$weekday, $weekend] = $period
                ? [$period->nightly_price, $period->weekend_nightly_price]
                : [$type->base_price, $type->weekend_price];

            $isWeekend = $night->isFriday() || $night->isSaturday();
            $rates[$date] = number_format((float) ($isWeekend && $weekend !== null ? $weekend : $weekday), 2, '.', '');

            if ($period) {
                $minStay = max($minStay, (int) $period->min_stay_nights);
            }
        }

        if (count($rates) < $minStay) {
            $this->fail('check_out', $type->name.' needs a minimum stay of '.$minStay.' nights for these dates.');
        }

        return ['rates' => $rates, 'total' => round(array_sum(array_map('floatval', $rates)), 2)];
    }

    /** Move a booking along the state machine, releasing inventory as needed. */
    public function transition(Booking $booking, string $to, ?string $reason = null): Booking
    {
        if (! $booking->canTransitionTo($to)) {
            $this->fail('status', 'A '.strtolower($booking->statusLabel()).' booking cannot be marked '.str_replace('_', ' ', $to).'.');
        }

        if (in_array($to, [Booking::CHECKED_IN, Booking::NO_SHOW], true) && $booking->check_in->isFuture()) {
            $this->fail('status', 'This can only be done from the check-in date ('.$booking->check_in->format('M j, Y').').');
        }

        $from = $booking->status;

        BookingTransitioning::dispatch($booking, $to);

        DB::transaction(function () use ($booking, $from, $to, $reason): void {
            $booking->status = $to;

            match ($to) {
                Booking::CONFIRMED => $booking->forceFill(['confirmed_at' => now(), 'hold_expires_at' => null]),
                Booking::CHECKED_IN => $booking->forceFill(['checked_in_at' => now()]),
                Booking::CHECKED_OUT => $booking->forceFill(['checked_out_at' => now()]),
                Booking::CANCELLED => $booking->forceFill(['cancelled_at' => now(), 'cancellation_reason' => $reason]),
                default => null,
            };

            if (in_array($from, Booking::OCCUPYING, true) && ! in_array($to, Booking::OCCUPYING, true)) {
                // Early departure frees the remaining nights (stayed nights
                // stay on the calendar); cancel / no-show frees all of them.
                DB::table('room_nights')
                    ->whereIn('booking_room_id', $booking->rooms()->pluck('id'))
                    ->when($to === Booking::CHECKED_OUT, fn ($q) => $q->where('night', '>=', today()->toDateString()))
                    ->delete();
            }

            $booking->save();
        });

        $this->audit->log('booking.'.$to, $booking, ['status' => $from], ['status' => $to, 'reason' => $reason]);

        BookingTransitioned::dispatch($booking, $from, $to);

        if (in_array($to, [Booking::CONFIRMED, Booking::CANCELLED], true)) {
            $booking->customer?->notify(new BookingStatusChanged($booking));
        }

        return $booking;
    }

    /** Cancel holds whose time ran out so their rooms go back on sale. */
    public function releaseExpiredHolds(Property $property): int
    {
        $expired = Booking::query()
            ->where('property_id', $property->getKey())
            ->where('status', Booking::HELD)
            ->where('hold_expires_at', '<', now())
            ->get();

        $expired->each(fn (Booking $booking) => $this->transition($booking, Booking::CANCELLED, 'Hold expired'));

        return $expired->count();
    }

    /**
     * Run a callback inside the tenant that owns $model — used by the
     * marketplace and customer portal, which have no tenant session.
     */
    public function asTenantOf(Model $model, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAs($model, $callback);
    }

    private function promotionFor(Property $property, ?string $code, CarbonImmutable $checkIn, int $nights): ?Promotion
    {
        if (blank($code)) {
            return null;
        }

        $promotion = Promotion::lookup($code, Promotion::FOR_STAYS);

        $rejection = $promotion
            ? ($promotion->couponRejection() ?? $promotion->rejectionFor((int) $property->getKey(), $checkIn->toDateString(), $nights))
            : 'This promo code does not exist.';

        if ($rejection !== null) {
            $this->fail('promo_code', $rejection);
        }

        return $promotion;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
