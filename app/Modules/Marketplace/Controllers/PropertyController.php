<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Services\FavoriteService;
use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Property detail page (/property/{slug}).
 *
 * Unpublished or soft-deleted rows are invisible to the public: the lookup
 * goes through Property::publicQuery() and a miss is a plain 404, so the
 * page cannot be used to probe for drafts.
 */
class PropertyController extends Controller
{
    public function __construct(
        private readonly MarketplaceSearchService $search,
        private readonly FavoriteService $favorites,
    ) {}

    public function show(Request $request, string $property): View
    {
        $listing = Property::publicQuery()
            ->with([
                'amenities' => fn ($q) => $q->orderBy('amenities.sort_order'),
                'reviews' => fn ($q) => $q->published()->with('user')->limit(8),
            ])
            ->where('slug', $property)
            ->firstOrFail();

        $isFavorited = $request->user()
            ? in_array($listing->id, $this->favorites->favoritedIds($request->user(), $listing->getMorphClass(), [$listing->id]), true)
            : false;

        return view('marketplace::properties.show', [
            'property' => $listing,
            'similar' => $this->search->similarProperties($listing),
            'isFavorited' => $isFavorited,
            'title' => $listing->name,
        ]);
    }
}