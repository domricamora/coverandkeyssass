<?php

namespace App\Modules\PropertyManagement\Services;

use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\AvailabilityBlock;
use App\Modules\PropertyManagement\Models\Room;
use App\Modules\PropertyManagement\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Resolves sellable room inventory for a date window.
 *
 * Semantics: dates are inclusive; a room is unsellable for a window when
 * any availability block intersects it — either a block on the room itself
 * or a block covering its whole room type. The booking engine (Phase 05)
 * builds on these primitives; never let reservations bypass them.
 */
class AvailabilityService
{
    /**
     * IDs of the property's rooms that are NOT sellable for at least one
     * night in the inclusive window.
     *
     * @return list<int>
     */
    public function blockedRoomIds(Property $property, string|CarbonImmutable $from, string|CarbonImmutable $to): array
    {
        [$from, $to] = $this->normalise($from, $to);

        $blocks = AvailabilityBlock::query()
            ->where('property_id', $property->getKey())
            ->intersecting($from->toDateString(), $to->toDateString())
            ->get(['room_type_id', 'room_id']);

        if ($blocks->isEmpty()) {
            return [];
        }

        // Blocks covering a whole room type take out every room of that type.
        $blocked = Room::query()
            ->where('property_id', $property->getKey())
            ->whereIn('room_type_id', $blocks->whereNull('room_id')->pluck('room_type_id')->unique())
            ->pluck('id');

        // Plus the rooms individually blocked.
        $blocked = $blocked->merge($blocks->whereNotNull('room_id')->pluck('room_id'));

        return $blocked->unique()->values()->all();
    }

    /**
     * How many sellable rooms of a room type remain for the inclusive
     * window (total active rooms minus rooms blocked for any night).
     */
    public function availableRoomCount(RoomType $roomType, string|CarbonImmutable $from, string|CarbonImmutable $to): int
    {
        return count($this->freeRoomIds($roomType, $from, $to));
    }

    /**
     * Sellable rooms of a room type free for every night of the inclusive
     * window: not blocked and not occupied by a booking (room_nights).
     *
     * @return list<int>
     */
    public function freeRoomIds(RoomType $roomType, string|CarbonImmutable $from, string|CarbonImmutable $to): array
    {
        [$from, $to] = $this->normalise($from, $to);

        $sellable = $roomType->rooms()->sellable()->orderBy('id')->pluck('id');

        if ($sellable->isEmpty()) {
            return [];
        }

        $blocks = AvailabilityBlock::query()
            ->where('room_type_id', $roomType->getKey())
            ->intersecting($from->toDateString(), $to->toDateString())
            ->get(['room_id']);

        // A single whole-type block takes the room type off the market.
        if ($blocks->contains(fn (AvailabilityBlock $block) => $block->room_id === null)) {
            return [];
        }

        $booked = DB::table('room_nights')
            ->whereIn('room_id', $sellable)
            ->whereBetween('night', [$from->toDateString(), $to->toDateString()])
            ->distinct()
            ->pluck('room_id');

        return $sellable->diff($blocks->pluck('room_id'))->diff($booked)->values()->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function normalise(string|CarbonImmutable $from, string|CarbonImmutable $to): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
