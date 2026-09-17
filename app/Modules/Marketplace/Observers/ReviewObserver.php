<?php

namespace App\Modules\Marketplace\Observers;

use App\Modules\Marketplace\Models\Review;
use App\Modules\Marketplace\Services\ListingMetricsService;

/**
 * Keeps the reviewed listing's rating columns in step with its published
 * reviews (created, moderated, unpublished, deleted or restored).
 */
class ReviewObserver
{
    public function __construct(private readonly ListingMetricsService $metrics) {}

    public function saved(Review $review): void
    {
        $this->refresh($review);
    }

    public function deleted(Review $review): void
    {
        $this->refresh($review);
    }

    public function restored(Review $review): void
    {
        $this->refresh($review);
    }

    private function refresh(Review $review): void
    {
        $listing = $review->reviewable;

        if ($listing) {
            $this->metrics->recalculateRating($listing);
        }
    }
}