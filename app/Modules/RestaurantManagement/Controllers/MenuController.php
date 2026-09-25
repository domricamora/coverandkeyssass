<?php

namespace App\Modules\RestaurantManagement\Controllers;

use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menu builder (Phase 09): categories → items → modifier groups → options.
 * Every nested row is reached through the tenant-scoped restaurant.
 */
class MenuController extends RestaurantManagementController
{
    public function index(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'menu.view');
        $restaurant = $this->resolveRestaurant($restaurant);

        return view('restaurant-management::restaurants.menu', [
            'restaurant' => $restaurant,
            'categories' => $restaurant->menuCategories()->with('items.modifierGroups.options')->get(),
            'title' => 'Menu — '.$restaurant->name,
        ]);
    }

    // Categories ---------------------------------------------------------

    public function storeCategory(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->menuCategories()->create($request->validate($this->categoryRules($restaurant)));

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, string $restaurant, string $category)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $category = $restaurant->menuCategories()->findOrFail($category);

        $validated = $request->validate($this->categoryRules($restaurant, $category->getKey()));
        $category->update($validated + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(Request $request, string $restaurant, string $category)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $category = $restaurant->menuCategories()->findOrFail($category);

        if ($category->items()->exists()) {
            return back()->withErrors(['category' => 'Move or remove the items in "'.$category->name.'" first.']);
        }

        $category->delete();

        return back()->with('success', 'Category removed.');
    }

    // Items --------------------------------------------------------------

    public function storeItem(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->menuItems()->create($request->validate($this->itemRules($restaurant)));

        return back()->with('success', 'Menu item added.');
    }

    public function updateItem(Request $request, string $restaurant, string $item)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $item = $restaurant->menuItems()->findOrFail($item);

        $item->update($request->validate($this->itemRules($restaurant)) + ['is_available' => $request->boolean('is_available')]);

        return back()->with('success', 'Menu item updated.');
    }

    public function destroyItem(Request $request, string $restaurant, string $item)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->menuItems()->findOrFail($item)->delete();

        return back()->with('success', 'Menu item removed.');
    }

    // Modifier groups + options ------------------------------------------

    public function storeGroup(Request $request, string $restaurant, string $item)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $item = $restaurant->menuItems()->findOrFail($item);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'min_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_select' => ['nullable', 'integer', 'min:1', 'max:20', 'gte:min_select'],
        ]);

        $item->modifierGroups()->create(['min_select' => (int) ($validated['min_select'] ?? 0)] + $validated);

        return back()->with('success', 'Modifier group added.');
    }

    public function destroyGroup(Request $request, string $restaurant, string $item, string $group)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->menuItems()->findOrFail($item)->modifierGroups()->findOrFail($group)->delete();

        return back()->with('success', 'Modifier group removed.');
    }

    public function storeOption(Request $request, string $restaurant, string $item, string $group)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $group = $restaurant->menuItems()->findOrFail($item)->modifierGroups()->findOrFail($group);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $group->options()->create(['price' => $validated['price'] ?? 0] + $validated);

        return back()->with('success', 'Option added.');
    }

    public function destroyOption(Request $request, string $restaurant, string $item, string $group, string $option)
    {
        $this->authorizeTo($request, 'menu.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->menuItems()->findOrFail($item)
            ->modifierGroups()->findOrFail($group)
            ->options()->findOrFail($option)
            ->delete();

        return back()->with('success', 'Option removed.');
    }

    // --------------------------------------------------------------------

    private function categoryRules(Restaurant $restaurant, ?int $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120',
                Rule::unique('menu_categories', 'name')->where('restaurant_id', $restaurant->getKey())->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function itemRules(Restaurant $restaurant): array
    {
        return [
            'menu_category_id' => ['required', 'integer',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', $restaurant->getKey())],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
