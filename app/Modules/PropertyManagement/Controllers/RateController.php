<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\RatePeriod;
use App\Modules\PropertyManagement\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Rate periods (Phase 04): date-range price overrides per room type.
 * Overlapping ranges are rejected so every night resolves to one rate.
 */
class RateController extends PropertyManagementController
{
    public function store(Request $request, string $property, string $roomType)
    {
        [$property, $type] = $this->resolveType($property, $roomType);
        $this->authorizeProperty($request, $property, 'rates.manage');

        $validated = $request->validate($this->rules());

        $overlap = RatePeriod::query()
            ->where('room_type_id', $type->getKey())
            ->overlapping($validated['start_date'], $validated['end_date'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'This range overlaps an existing rate period for this room type.',
            ]);
        }

        $rate = $type->ratePeriods()->create($validated);

        return back()->with('success', 'Rate "'.$rate->rangeLabel().'" added.');
    }

    public function destroy(Request $request, string $property, string $roomType, string $ratePeriod)
    {
        [$property, $type] = $this->resolveType($property, $roomType);
        $this->authorizeProperty($request, $property, 'rates.manage');

        $rate = $type->ratePeriods()->findOrFail($ratePeriod);

        $rate->delete();

        return back()->with('success', 'Rate period removed.');
    }

    /** @return array{0: Property, 1: RoomType} */
    private function resolveType(string $property, string $roomType): array
    {
        $property = $this->resolveProperty($property);
        $type = $property->roomTypes()->findOrFail($roomType);

        return [$property, $type];
    }

    private function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'nightly_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'weekend_nightly_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'min_stay_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
