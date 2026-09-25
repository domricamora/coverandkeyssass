<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Support\Present;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Requests\PropertySearchRequest;
use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Public marketplace catalogue. Reads only published listings through the
 * same publicQuery() / search service as the website, so drafts and
 * suspended listings are a 404 here too.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly MarketplaceSearchService $search) {}

    public function properties(PropertySearchRequest $request): JsonResponse
    {
        return response()->json($this->search->searchProperties($request->validated())->through(fn ($p) => Present::property($p)));
    }

    public function property(string $slug): JsonResponse
    {
        $property = $this->findProperty($slug)->load('amenities');

        return response()->json(['data' => Present::property($property) + [
            'description' => $property->description,
            'check_in_time' => $property->check_in_time,
            'check_out_time' => $property->check_out_time,
            'amenities' => $property->amenities->pluck('name'),
            'photos' => array_map(fn ($url) => str_starts_with($url, 'http') ? $url : url($url), $property->galleryUrls()),
        ]]);
    }

    public function rooms(string $slug, BookingService $bookings): JsonResponse
    {
        $property = $this->findProperty($slug);
        $types = $bookings->asTenantOf($property, fn () => $property->roomTypes()->active()->sorted()->get());

        return response()->json(['data' => $types->map(fn ($t) => Present::roomType($t))->values()]);
    }

    public function propertyReviews(string $slug): JsonResponse
    {
        $reviews = $this->findProperty($slug)->reviews()->published()->with('user')->paginate(20);

        return response()->json($reviews->through(fn ($r) => Present::review($r)));
    }

    public function restaurants(PropertySearchRequest $request): JsonResponse
    {
        return response()->json($this->search->searchRestaurants($request->validated())->through(fn ($r) => Present::restaurant($r)));
    }

    public function restaurant(string $slug): JsonResponse
    {
        $restaurant = $this->findRestaurant($slug);

        return response()->json(['data' => Present::restaurant($restaurant) + [
            'description' => $restaurant->description,
            'phone' => $restaurant->phone,
            'opening_hours' => $restaurant->opening_hours,
            'photos' => array_map(fn ($url) => str_starts_with($url, 'http') ? $url : url($url), $restaurant->galleryUrls()),
        ]]);
    }

    public function menu(string $slug): JsonResponse
    {
        // Same bounded scope-drop as the website: the parent is already public.
        $restaurant = $this->findRestaurant($slug)->load([
            'menuCategories' => fn ($q) => $q->withoutGlobalScope('tenant')->where('is_active', true)->orderBy('sort_order'),
            'menuCategories.items' => fn ($q) => $q->withoutGlobalScope('tenant')->where('is_available', true)->orderBy('sort_order'),
            'menuCategories.items.modifierGroups' => fn ($q) => $q->withoutGlobalScope('tenant'),
            'menuCategories.items.modifierGroups.options' => fn ($q) => $q->withoutGlobalScope('tenant')->where('is_available', true),
        ]);

        return response()->json(['data' => $restaurant->menuCategories->map(fn ($category) => [
            'name' => $category->name,
            'items' => $category->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => Present::money($item->price, $item->currency),
                'modifier_groups' => $item->modifierGroups->map(fn ($group) => [
                    'name' => $group->name,
                    'min' => $group->min_select,
                    'max' => $group->max_select,
                    'options' => $group->options->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'price' => Present::money($o->price, $item->currency)])->values(),
                ])->values(),
            ])->values(),
        ])->values()]);
    }

    private function findProperty(string $slug): Property
    {
        return Property::publicQuery()->where('slug', $slug)->firstOrFail();
    }

    private function findRestaurant(string $slug): Restaurant
    {
        return Restaurant::publicQuery()->where('slug', $slug)->firstOrFail();
    }
}
