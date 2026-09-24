<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Modules\Marketplace\Models\Property;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * The inventory workbench for one property: room types with their rooms,
 * rate periods and availability blocks on a single screen.
 */
class InventoryController extends Controller
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

        return view('property-management::properties.inventory', [
            'property' => $property,
            'roomTypes' => $roomTypes,
            'blocks' => $blocks,
            'title' => 'Inventory — '.$property->name,
        ]);
    }
}
