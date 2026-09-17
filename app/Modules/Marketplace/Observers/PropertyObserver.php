<?php

namespace App\Modules\Marketplace\Observers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Services\ListingMetricsService;

/** Keeps destination counters in step as properties are published or removed. */
class PropertyObserver
{
    public function __construct(private readonly ListingMetricsService $metrics) {}

    public function saved(Property $property): void
    {
        $this->refresh($property);
    }

    public function deleted(Property $property): void
    {
        $this->refresh($property);
    }

    public function restored(Property $property): void
    {
        $this->refresh($property);
    }

    private function refresh(Property $property): void
    {
        if ($property->location_id) {
            $this->metrics->refreshLocationCounts($property->location_id);
        }
    }
}