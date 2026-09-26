<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\AvailabilityBlock;
use App\Modules\PropertyManagement\Models\Room;
use Illuminate\Http\Request;

/**
 * The inventory workbench for one property: room types with their rooms,
 * rate periods and availability blocks on a single screen.
 */
class InventoryController extends PropertyManagementController
{
    public function index(Request $request, string $property)
    {
        $property = Property::query()->where('slug', $property)->firstOrFail();

        $tenantId = app(\App\Support\TenantContext::class)->id();
        abort_unless($tenantId !== null && (int) $property->tenant_id === (int) $tenantId, 404);

        $this->authorize('view', $property);

        $roomTypes = $property->roomTypes()
            ->with([
                'rooms' => fn ($query) => $query->orderBy('room_number'),
                'ratePeriods',
            ])
            ->withCount(['rooms', 'ratePeriods'])
            ->sorted()
            ->get();

        $blocks = $property->availabilityBlocks()
            ->with(['roomType', 'room'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $p = $property;

        return \Inertia\Inertia::render('Properties/Inventory', [
            'property' => ['name' => $p->name],
            'tabs' => $this->tabs($p, 'inventory'),
            'roomTypes' => $roomTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'summary' => $type->max_guests.' guests · '.$type->priceLabel().'/night · min '.$type->min_stay_nights.' night(s) · '.ucfirst($type->status),
                'status' => $type->status,
                'destroy' => route('properties.room-types.destroy', [$p, $type]),
                'addRoom' => route('properties.rooms.store', [$p, $type]),
                'addRate' => route('properties.rates.store', [$p, $type]),
                'rooms' => $type->rooms->map(fn ($room) => [
                    'id' => $room->id,
                    'label' => $room->label(),
                    'floor' => $room->floor,
                    'status' => $room->status,
                    'update' => route('properties.rooms.update', [$p, $type, $room]),
                    'destroy' => route('properties.rooms.destroy', [$p, $type, $room]),
                ]),
                'rates' => $type->ratePeriods->map(fn ($rate) => [
                    'id' => $rate->id,
                    'range' => $rate->rangeLabel(),
                    'nightly' => $type->currency.' '.number_format((float) $rate->nightly_price, 0),
                    'weekend' => $rate->weekend_nightly_price ? number_format((float) $rate->weekend_nightly_price, 0) : null,
                    'min' => $rate->min_stay_nights,
                    'destroy' => route('properties.rates.destroy', [$p, $type, $rate]),
                ]),
            ]),
            'blocks' => $blocks->map(fn ($b) => [
                'id' => $b->id,
                'target' => ($b->roomType?->name ?? '—').($b->room ? ' · room '.$b->room->room_number : ''),
                'range' => $b->rangeLabel(),
                'reason' => $b->reason,
                'note' => $b->note,
                'destroy' => route('properties.availability.destroy', [$p, $b]),
            ]),
            'roomStatuses' => Room::statuses(),
            'reasons' => AvailabilityBlock::reasons(),
            'can' => ['edit' => $request->user()->can('update', $p)],
            'urls' => ['addType' => route('properties.room-types.store', $p), 'addBlock' => route('properties.availability.store', $p)],
        ]);
    }
}
