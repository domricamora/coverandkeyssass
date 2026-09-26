<?php

namespace App\Modules\Marketplace\Controllers;

use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Requests\PropertySearchRequest;
use App\Modules\Marketplace\Services\FavoriteService;
use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Public restaurant directory: /restaurants and /restaurant/{slug}. */
class RestaurantController extends Controller
{
    public function __construct(
        private readonly MarketplaceSearchService $search,
        private readonly FavoriteService $favorites,
    ) {}

    public function index(PropertySearchRequest $request): View
    {
        $filters = $request->validated();

        $restaurants = $this->search->searchRestaurants($filters);

        return view('marketplace::restaurants.index', [
            'restaurants' => $restaurants,
            'filters' => $filters,
            'options' => $this->search->filterOptions(),
            'favoriteIds' => $this->favoriteIds($request->user(), $restaurants->getCollection()),
            'title' => 'Restaurants in the Philippines: Reserve a Table or Order Online',
        ]);
    }

    public function show(Request $request, string $restaurant): View
    {
        $listing = Restaurant::publicQuery()
            ->with(['reviews' => fn ($q) => $q->published()->with('user')->limit(6)])
            // Menu rows are tenant-owned; the parent listing is already
            // restricted to published, so dropping the scope per level is
            // bounded to this restaurant's menu.
            ->with([
                'menuCategories' => fn ($q) => $q->withoutGlobalScope('tenant')->where('is_active', true),
                'menuCategories.items' => fn ($q) => $q->withoutGlobalScope('tenant'),
                'menuCategories.items.modifierGroups' => fn ($q) => $q->withoutGlobalScope('tenant'),
                'menuCategories.items.modifierGroups.options' => fn ($q) => $q->withoutGlobalScope('tenant')->where('is_available', true),
            ])
            ->where('slug', $restaurant)
            ->firstOrFail();

        $isFavorited = $request->user()
            ? in_array($listing->id, $this->favorites->favoritedIds($request->user(), $listing->getMorphClass(), [$listing->id]), true)
            : false;

        return view('marketplace::restaurants.show', [
            'listing' => $listing,
            'isFavorited' => $isFavorited,
            'title' => $listing->name,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Restaurant>  $restaurants
     * @return array<int, int>
     */
    private function favoriteIds(?User $user, $restaurants): array
    {
        return $this->favorites->favoritedIds(
            $user,
            (new Restaurant)->getMorphClass(),
            $restaurants->pluck('id')->all(),
        );
    }
}