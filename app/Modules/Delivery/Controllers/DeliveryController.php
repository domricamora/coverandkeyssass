<?php

namespace App\Modules\Delivery\Controllers;

use App\Modules\Delivery\Models\Driver;
use App\Modules\RestaurantManagement\Controllers\RestaurantManagementController;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Delivery setup per restaurant (Phase 12): zones (fee, minimum, free-over,
 * radius, ETA), kitchen prep time and the business's drivers.
 */
class DeliveryController extends RestaurantManagementController
{
    public function index(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'delivery.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        return view('delivery::index', [
            'restaurant' => $restaurant,
            'zones' => $restaurant->deliveryZones()->orderBy('sort_order')->orderBy('name')->get(),
            'drivers' => Driver::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'title' => 'Delivery — '.$restaurant->name,
        ]);
    }

    public function storeZone(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'delivery.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->deliveryZones()->create($request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('delivery_zones', 'name')->where('restaurant_id', $restaurant->id)],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'fee' => ['required', 'numeric', 'min:0', 'max:99999'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'free_over' => ['nullable', 'numeric', 'min:0'],
            'eta_minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ]));

        return back()->with('success', 'Delivery zone added.');
    }

    public function toggleZone(Request $request, string $restaurant, string $zone)
    {
        $this->authorizeTo($request, 'delivery.manage');
        $zone = $this->resolveRestaurant($restaurant)->deliveryZones()->findOrFail($zone);

        $zone->update(['is_active' => ! $zone->is_active]);

        return back()->with('success', 'Zone '.$zone->name.' '.($zone->is_active ? 'reopened' : 'paused').'.');
    }

    public function destroyZone(Request $request, string $restaurant, string $zone)
    {
        $this->authorizeTo($request, 'delivery.manage');
        $this->resolveRestaurant($restaurant)->deliveryZones()->findOrFail($zone)->delete();

        return back()->with('success', 'Delivery zone removed.');
    }

    public function updateSettings(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'delivery.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->update($request->validate([
            'prep_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]));

        return back()->with('success', 'Delivery settings saved.');
    }

    public function storeDriver(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'delivery.manage');
        $this->resolveRestaurant($restaurant);

        Driver::create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'vehicle' => ['nullable', 'string', 'max:80'],
        ]));

        return back()->with('success', 'Driver added.');
    }

    public function toggleDriver(Request $request, string $restaurant, string $driver)
    {
        $this->authorizeTo($request, 'delivery.manage');
        $this->resolveRestaurant($restaurant);

        $driver = Driver::query()->findOrFail($driver);
        $driver->update(['is_active' => ! $driver->is_active]);

        return back()->with('success', $driver->name.' is now '.($driver->is_active ? 'active' : 'off duty').'.');
    }
}
