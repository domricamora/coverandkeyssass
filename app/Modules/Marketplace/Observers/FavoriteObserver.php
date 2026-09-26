<?php

namespace App\Modules\Marketplace\Observers;

use App\Modules\Marketplace\Models\Favorite;
use App\Modules\Marketplace\Services\ListingMetricsService;

/** Keeps a listing's favorites_count honest. */
class FavoriteObserver
{
    public function __construct(private readonly ListingMetricsService $metrics) {}

    /** Remember the stay's price when saved, for price-drop alerts. */
    public function creating(Favorite $favorite): void
    {
        $listing = $favorite->favoritable;

        if ($listing instanceof \App\Modules\Marketplace\Models\Property && $favorite->saved_price === null) {
            $favorite->saved_price = $listing->base_price;
        }
    }

    public function created(Favorite $favorite): void
    {
        $this->refresh($favorite);
    }

    public function deleted(Favorite $favorite): void
    {
        $this->refresh($favorite);
    }

    private function refresh(Favorite $favorite): void
    {
        $listing = $favorite->favoritable;

        if ($listing) {
            $this->metrics->syncFavoritesCount($listing);
        }
    }
}