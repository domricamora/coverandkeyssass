<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Room type CRUD under a property (Phase 04): the pricing and occupancy
 * template that rooms and rates hang off. Delete is blocked while physical
 * rooms still reference the type.
 */
class RoomTypeController extends PropertyManagementController
{
    public function store(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'rooms.create');

        $validated = $request->validate($this->rules($property));

        $roomType = $property->roomTypes()->create($validated);

        return back()->with('success', 'Room type "'.$roomType->name.'" added.');
    }

    public function update(Request $request, string $property, string $roomType)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'rooms.update');

        $type = $property->roomTypes()->findOrFail($roomType);

        $type->update($request->validate($this->rules($property, $type)));

        return back()->with('success', 'Room type updated.');
    }

    public function destroy(Request $request, string $property, string $roomType)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'rooms.delete');

        $type = $property->roomTypes()->findOrFail($roomType);

        if ($type->rooms()->exists()) {
            return back()->withErrors([
                'room_types' => 'Remove the rooms of this type first — '.$type->rooms()->count().' still assigned.',
            ]);
        }

        $type->ratePeriods()->delete();
        $type->delete();

        return back()->with('success', 'Room type removed.');
    }

    private function rules(Property $property, ?RoomType $roomType = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('room_types', 'name')
                    ->where(fn ($query) => $query
                        ->where('property_id', $property->getKey())
                        ->whereNull('deleted_at'))
                    ->ignore($roomType?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:99'],
            'beds' => ['nullable', 'integer', 'min:0', 'max:99'],
            'bed_configuration' => ['nullable', 'string', 'max:120'],
            'size_sqm' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'weekend_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'min_stay_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'status' => ['nullable', 'in:'.implode(',', RoomType::statuses())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
