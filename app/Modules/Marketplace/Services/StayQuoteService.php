<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Services\AvailabilityService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Date search for the marketplace (Booking.com / Airbnb style): which stays
 * have a room free for every night, and what the whole stay costs.
 *
 * Prices come from the booking engine's own quote (weekend and seasonal
 * rates, minimum stay), so the total shown is what the guest will pay.
 */
class StayQuoteService
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * Published-property ids with at least one sellable room free on every
     * night of [check_in, check_out). One query across all businesses.
     *
     * @return list<int>
     */
    public function availablePropertyIds(string $checkIn, string $checkOut): array
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

        return DB::table('rooms')
            ->join('room_types', 'room_types.id', '=', 'rooms.room_type_id')
            ->where('room_types.status', 'active')
            ->whereNull('room_types.deleted_at')
            ->whereNull('rooms.deleted_at')
            ->where('rooms.status', 'active')
            ->where('rooms.housekeeping_status', '!=', 'out_of_order')
            ->whereNotExists(fn ($q) => $q->from('room_nights')
                ->whereColumn('room_nights.room_id', 'rooms.id')
                ->whereBetween('room_nights.night', [$checkIn, $lastNight]))
            ->whereNotExists(fn ($q) => $q->from('availability_blocks')
                ->where(fn ($w) => $w->whereColumn('availability_blocks.room_id', 'rooms.id')
                    ->orWhere(fn ($t) => $t->whereNull('availability_blocks.room_id')->whereColumn('availability_blocks.room_type_id', 'rooms.room_type_id')))
                ->where('availability_blocks.start_date', '<=', $lastNight)
                ->where('availability_blocks.end_date', '>=', $checkIn))
            ->distinct()
            ->pluck('rooms.property_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Cheapest bookable room type for the dates and party size.
     *
     * @return array{total: float, nights: int, per_night: float, room_type: string, room_type_id: int, rooms_left: int, currency: string}|null
     */
    public function quote(Property $property, string $checkIn, string $checkOut, int $guests = 1): ?array
    {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();
        $nights = (int) $in->diffInDays($out, false);

        if ($nights < 1) {
            return null;
        }

        return app(TenantContext::class)->runAs($property, function () use ($property, $in, $out, $nights, $guests) {
            $best = null;
            $roomsLeft = 0;

            foreach ($property->roomTypes()->active()->where('max_guests', '>=', max(1, $guests))->get() as $type) {
                $free = count($this->availability->freeRoomIds($type, $in, $out->subDay()));

                if ($free === 0) {
                    continue;
                }

                try {
                    $quote = $this->bookings->quote($type, $in, $out);
                } catch (ValidationException) {
                    continue; // minimum stay not met for these dates
                }

                $roomsLeft += $free;

                if ($best === null || $quote['total'] < $best['total']) {
                    $best = [
                        'total' => $quote['total'],
                        'nights' => $nights,
                        'per_night' => round($quote['total'] / $nights, 2),
                        'room_type' => $type->name,
                        'room_type_id' => $type->id,
                        'currency' => $type->currency ?: $property->currency,
                    ];
                }
            }

            return $best ? $best + ['rooms_left' => $roomsLeft] : null;
        });
    }
}
