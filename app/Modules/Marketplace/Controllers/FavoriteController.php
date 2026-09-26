<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Marketplace\Models\Favorite;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Services\FavoriteService;
use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Guest wish list (/favorites).
 *
 * Only the authenticated user's own favorites are ever listed, created or
 * removed. Targets must be published listings, so a wish list can never
 * confirm the existence of a draft.
 */
class FavoriteController extends Controller
{
    /** Route parameter => model class. Anything else is rejected. */
    private const TYPES = [
        'property' => Property::class,
        'restaurant' => Restaurant::class,
    ];

    public function __construct(private readonly FavoriteService $favorites) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $favorites = Favorite::query()
            ->where('user_id', $user->id)
            ->with(['favoritable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Property::class => ['media', 'location', 'propertyType'],
                    Restaurant::class => ['media', 'location', 'cuisines'],
                ]);
            }])
            ->latest()
            ->paginate(MarketplaceSearchService::PER_PAGE)
            ->withQueryString();

        return \Inertia\Inertia::render('Account/WishList', [
            'items' => $favorites->through(function (Favorite $f) {
                $l = $f->favoritable;
                if (! $l) {
                    return null;
                }
                $stay = $l instanceof Property;

                return [
                    'id' => $f->id,
                    'kind' => $stay ? 'Stay' : 'Restaurant',
                    'name' => $l->name,
                    'where' => $l->locationLabel() ?: null,
                    'price' => $stay ? $l->priceLabel().' / night' : $l->priceLevelLabel(),
                    'image' => $l->galleryUrls()[0] ?? null,
                    'url' => route($stay ? 'marketplace.properties.show' : 'marketplace.restaurants.show', $l->slug),
                    'remove' => route('marketplace.favorites.destroy', [$l->getMorphClass(), $l->getKey()]),
                ];
            }),
            'tabs' => \App\Modules\Customer\Controllers\AccountController::nav('marketplace.favorites.index'),
            'urls' => ['stays' => route('marketplace.home')],
        ]);
    }

    public function store(Request $request, string $type, string $id): RedirectResponse
    {
        $listing = $this->resolveListing($type, $id);

        $this->favorites->add($request->user(), $listing);

        return back()->with('success', $listing->name.' saved to your wish list.');
    }

    public function destroy(Request $request, string $type, string $id): RedirectResponse
    {
        $listing = $this->resolveListing($type, $id);

        $this->favorites->remove($request->user(), $listing);

        return back()->with('success', $listing->name.' removed from your wish list.');
    }

    /**
     * Resolve a published listing of an allowed type, or fail with a 404
     * (unknown/forbidden type is a validation error, not a lookup).
     */
    private function resolveListing(string $type, string $id): Property|Restaurant
    {
        $class = self::TYPES[$type] ?? null;

        if ($class === null) {
            throw ValidationException::withMessages([
                'type' => 'Unsupported wish list item type.',
            ]);
        }

        /** @var Property|Restaurant $listing */
        $listing = $class::publicQuery()
            ->whereKey($this->numericId($id))
            ->firstOrFail();

        return $listing;
    }

    private function numericId(string $id): int
    {
        if (! ctype_digit($id)) {
            throw ValidationException::withMessages([
                'id' => 'Invalid listing identifier.',
            ]);
        }

        return (int) $id;
    }
}