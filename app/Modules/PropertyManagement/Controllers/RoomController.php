<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\Room;
use App\Modules\PropertyManagement\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Physical room inventory (Phase 04): the concrete units behind a room
 * type. Room numbers are unique per property among live rows.
 */
class RoomController extends PropertyManagementController
{
    public function store(Request $request, string $property, string $roomType)
    {
        [$property, $type] = $this->resolveType($property, $roomType);
        $this->authorizeProperty($request, $property, 'rooms.create');

        $validated = $request->validate($this->rules($property));

        $room = $type->rooms()->create($validated + ['property_id' => $property->getKey()]);

        return back()->with('success', 'Room '.$room->label().' added.');
    }

    public function update(Request $request, string $property, string $roomType, string $room)
    {
        [$property, $type, $room] = $this->resolveRoom($property, $roomType, $room);
        $this->authorizeProperty($request, $property, 'rooms.update');

        $room->update($request->validate($this->rules($property, $room)));

        return back()->with('success', 'Room updated.');
    }

    public function destroy(Request $request, string $property, string $roomType, string $room)
    {
        [$property, $type, $room] = $this->resolveRoom($property, $roomType, $room);
        $this->authorizeProperty($request, $property, 'rooms.delete');

        $room->delete();

        return back()->with('success', 'Room removed from inventory.');
    }

    /** @return array{0: Property, 1: RoomType} */
    private function resolveType(string $property, string $roomType): array
    {
        $property = $this->resolveProperty($property);
        $type = $property->roomTypes()->findOrFail($roomType);

        return [$property, $type];
    }

    /** @return array{0: Property, 1: RoomType, 2: Room} */
    private function resolveRoom(string $property, string $roomType, string $room): array
    {
        [$property, $type] = $this->resolveType($property, $roomType);
        $room = $type->rooms()->findOrFail($room);

        return [$property, $type, $room];
    }

    private function rules(Property $property, ?Room $room = null): array
    {
        return [
            'room_number' => [
                'required', 'string', 'max:20',
                Rule::unique('rooms', 'room_number')
                    ->where(fn ($query) => $query
                        ->where('property_id', $property->getKey())
                        ->whereNull('deleted_at'))
                    ->ignore($room?->getKey()),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'floor' => ['nullable', 'integer', 'min:-10', 'max:200'],
            'status' => ['nullable', 'in:'.implode(',', Room::statuses())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
