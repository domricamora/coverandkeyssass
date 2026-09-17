<?php

namespace App\Modules\Marketplace\Observers;

use App\Modules\Marketplace\Models\Favorite;
use App\Modules\Marketplace\Services\ListingMetricsService;

/** Keeps a listing's favorites_count honest. */
class FavoriteObserver
{
    public function __construct(private readonly ListingMetricsService $metrics) {}

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