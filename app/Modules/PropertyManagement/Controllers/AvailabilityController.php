<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\AvailabilityBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Availability blocks (Phase 04): take a room type or a single room off
 * the market for an inclusive date range. The AvailabilityService is the
 * single source of truth the booking engine will consume.
 */
class AvailabilityController extends PropertyManagementController
{
    public function store(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'availability.manage');

        $validated = $request->validate($this->rules($property));

        // A room-level block must target a room of the chosen room type.
        if (! empty($validated['room_id'])) {
            $room = $property->rooms()->findOrFail($validated['room_id']);

            if ((int) $room->room_type_id !== (int) $validated['room_type_id']) {
                throw ValidationException::withMessages([
                    'room_id' => 'The selected room does not belong to the selected room type.',
                ]);
            }
        }

        $property->availabilityBlocks()->create($validated);

        $target = isset($validated['room_id']) ? 'room' : 'room type';

        return back()->with('success', 'Availability block added — '.$target.' is off the market for that range.');
    }

    public function destroy(Request $request, string $property, string $availabilityBlock)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'availability.manage');

        $block = $property->availabilityBlocks()->findOrFail($availabilityBlock);

        $block->delete();

        return back()->with('success', 'Availability block removed — dates are open again.');
    }

    private function rules(Property $property): array
    {
        return [
            'room_type_id' => [
                'required', 'integer',
                Rule::in($property->roomTypes()->pluck('id')),
            ],
            'room_id' => [
                'nullable', 'integer',
                Rule::in($property->rooms()->pluck('id')),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'in:'.implode(',', AvailabilityBlock::reasons())],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
