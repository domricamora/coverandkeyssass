<?php

namespace App\Modules\Marketplace\Controllers;

use App\Models\User;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Requests\PropertySearchRequest;
use App\Modules\Marketplace\Services\FavoriteService;
use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Public stay search: /hotels (filters, sorting, pagination) and
 * /hotels/{location} (destination landing page for SEO).
 */
class PropertySearchController extends Controller
{
    public function __construct(
        private readonly MarketplaceSearchService $search,
        private readonly FavoriteService $favorites,
    ) {}

    public function index(PropertySearchRequest $request): View
    {
        $filters = $request->validated();

        $properties = $this->search->searchProperties($filters);

        return view('marketplace::properties.index', [
            'properties' => $properties,
            'filters' => $filters,
            'options' => $this->search->filterOptions(),
            'destination' => null,
            'favoriteIds' => $this->favoriteIds($request->user(), $properties->getCollection()),
            'title' => 'Stays',
        ]);
    }

    public function location(PropertySearchRequest $request, string $location): View
    {
        $destination = Location::query()->where('slug', $location)->firstOrFail();

        $filters = $request->validated();

        $properties = $this->search->searchPropertiesInLocation($destination, $filters);

        return view('marketplace::properties.index', [
            'properties' => $properties,
            'filters' => $filters + ['location' => $destination->slug],
            'options' => $this->search->filterOptions(),
            'destination' => $destination,
            'favoriteIds' => $this->favoriteIds($request->user(), $properties->getCollection()),
            'title' => 'Stays in '.$destination->name,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Property>  $properties
     * @return array<int, int>
     */
    private function favoriteIds(?User $user, $properties): array
    {
        return $this->favorites->favoritedIds(
            $user,
            (new Property)->getMorphClass(),
            $properties->pluck('id')->all(),
        );
    }
}