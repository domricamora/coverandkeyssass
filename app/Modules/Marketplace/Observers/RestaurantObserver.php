<?php

namespace App\Modules\Marketplace\Observers;

use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Services\ListingMetricsService;

/** Keeps destination counters in step as restaurants are published or removed. */
class RestaurantObserver
{
    public function __construct(private readonly ListingMetricsService $metrics) {}

    public function saved(Restaurant $restaurant): void
    {
        $this->refresh($restaurant);
    }

    public function deleted(Restaurant $restaurant): void
    {
        $this->refresh($restaurant);
    }

    public function restored(Restaurant $restaurant): void
    {
        $this->refresh($restaurant);
    }

    private function refresh(Restaurant $restaurant): void
    {
        if ($restaurant->location_id) {
            $this->metrics->refreshLocationCounts($restaurant->location_id);
        }
    }
}