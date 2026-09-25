<?php

namespace App\Modules\RestaurantManagement\Controllers;

use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Floor plan (Phase 09): dining areas and the tables inside them. */
class TableController extends RestaurantManagementController
{
    public function index(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'tables.view');
        $restaurant = $this->resolveRestaurant($restaurant);

        return view('restaurant-management::restaurants.tables', [
            'restaurant' => $restaurant,
            'areas' => $restaurant->diningAreas()->get(),
            'tables' => $restaurant->tables()->with('area')->get(),
            'title' => 'Tables — '.$restaurant->name,
        ]);
    }

    public function storeArea(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'tables.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->diningAreas()->create($request->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('dining_areas', 'name')->where('restaurant_id', $restaurant->getKey())],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('success', 'Dining area added.');
    }

    /** Removing an area keeps its tables, unassigned (FK nullOnDelete). */
    public function destroyArea(Request $request, string $restaurant, string $area)
    {
        $this->authorizeTo($request, 'tables.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->diningAreas()->findOrFail($area)->delete();

        return back()->with('success', 'Dining area removed.');
    }

    public function storeTable(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'tables.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->tables()->create($request->validate($this->tableRules($restaurant)));

        return back()->with('success', 'Table added.');
    }

    public function updateTable(Request $request, string $restaurant, string $table)
    {
        $this->authorizeTo($request, 'tables.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $table = $restaurant->tables()->findOrFail($table);

        $table->update($request->validate($this->tableRules($restaurant, $table->getKey())));

        return back()->with('success', 'Table updated.');
    }

    public function destroyTable(Request $request, string $restaurant, string $table)
    {
        $this->authorizeTo($request, 'tables.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->tables()->findOrFail($table)->delete();

        return back()->with('success', 'Table removed.');
    }

    private function tableRules(Restaurant $restaurant, ?int $ignore = null): array
    {
        return [
            'label' => ['required', 'string', 'max:40',
                Rule::unique('restaurant_tables', 'label')->where('restaurant_id', $restaurant->getKey())->ignore($ignore)],
            'seats' => ['required', 'integer', 'min:1', 'max:50'],
            'dining_area_id' => ['nullable', 'integer',
                Rule::exists('dining_areas', 'id')->where('restaurant_id', $restaurant->getKey())],
            'status' => ['nullable', Rule::in([RestaurantTable::STATUS_ACTIVE, RestaurantTable::STATUS_INACTIVE])],
        ];
    }
}
