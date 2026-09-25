<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\MenuItemIngredient;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Support\Unit;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Menu-item recipes (Phase 18): which stock a dish uses, and the kitchen it is taken from. */
class RecipeController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'inventory.view');

        $restaurants = Restaurant::query()->orderBy('name')->get(['id', 'name', 'slug', 'stock_location_id']);
        $restaurant = $restaurants->firstWhere('slug', $request->query('restaurant')) ?? $restaurants->first();

        return view('inventory::recipes', [
            'restaurants' => $restaurants,
            'restaurant' => $restaurant,
            'menuItems' => $restaurant ? $restaurant->menuItems()->orderBy('name')->get() : collect(),
            'ingredients' => $restaurant ? MenuItemIngredient::query()->whereIn('menu_item_id', $restaurant->menuItems()->pluck('id'))->with('item')->get()->groupBy('menu_item_id') : collect(),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => StockLocation::query()->orderBy('name')->get(),
            'units' => Unit::all(),
            'title' => 'Recipes',
        ]);
    }

    public function setLocation(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'inventory.manage');
        $restaurant = Restaurant::query()->where('slug', $restaurant)->firstOrFail();
        $locationId = $request->validate(['stock_location_id' => ['nullable', 'integer']])['stock_location_id'] ?? null;

        $restaurant->forceFill(['stock_location_id' => $locationId ? StockLocation::query()->findOrFail($locationId)->id : null])->save();

        return back()->with('success', 'Kitchen stock location saved.');
    }

    public function store(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'inventory.manage');
        $restaurant = Restaurant::query()->where('slug', $restaurant)->firstOrFail();

        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer'],
            'inventory_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', Rule::in(Unit::all())],
        ]);

        $this->inventory->setIngredient(
            $restaurant->menuItems()->findOrFail($validated['menu_item_id']),
            InventoryItem::query()->findOrFail($validated['inventory_item_id']),
            (float) $validated['quantity'], $validated['unit'],
        );

        return back()->with('success', 'Ingredient saved.');
    }

    public function destroy(Request $request, string $restaurant, string $ingredient)
    {
        $this->authorizeTo($request, 'inventory.manage');
        $restaurant = Restaurant::query()->where('slug', $restaurant)->firstOrFail();

        MenuItemIngredient::query()->whereIn('menu_item_id', $restaurant->menuItems()->pluck('id'))->findOrFail($ingredient)->delete();

        return back()->with('success', 'Ingredient removed.');
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
