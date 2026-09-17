<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the denormalised marketplace counters truthful.
 *
 * Ratings and favourite counts are cached on the listing row so search
 * results and cards render without per-row aggregate queries. They are
 * always derived from the source tables — never incremented blindly — so a
 * repeated recalculation is safe and idempotent.
 */
class ListingMetricsService
{
    /**
     * Recompute reviews_count + avg_rating from published reviews.
     */
    public function recalculateRating(Model $listing): void
    {
        if (! $this->supports($listing, 'reviews_count')) {
            return;
        }

        $aggregate = DB::table('reviews')
            ->where('reviewable_type', $listing->getMorphClass())
            ->where('reviewable_id', $listing->getKey())
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as average')
            ->first();

        $listing->forceFill([
            'reviews_count' => (int) ($aggregate->total ?? 0),
            'avg_rating' => round((float) ($aggregate->average ?? 0), 2),
        ])->saveQuietly();
    }

    /**
     * Recompute the favourites counter for a listing.
     */
    public function syncFavoritesCount(Model $listing): void
    {
        if (! $this->supports($listing, 'favorites_count')) {
            return;
        }

        $count = DB::table('favorites')
            ->where('favoritable_type', $listing->getMorphClass())
            ->where('favoritable_id', $listing->getKey())
            ->count();

        $listing->forceFill(['favorites_count' => $count])->saveQuietly();
    }

    /**
     * Recompute a destination's listing counters (published rows only).
     */
    public function refreshLocationCounts(Location|int $location): void
    {
        $locationId = $location instanceof Location ? $location->id : $location;

        $properties = DB::table('properties')
            ->where('location_id', $locationId)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->count();

        $restaurants = DB::table('restaurants')
            ->where('location_id', $locationId)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->count();

        DB::table('locations')->where('id', $locationId)->update([
            'properties_count' => $properties,
            'restaurants_count' => $restaurants,
            'updated_at' => now(),
        ]);
    }

    /**
     * Guard so the service stays silent for models that do not carry the
     * counter columns (e.g. a future menu item that only tracks reviews).
     */
    private function supports(Model $model, string $column): bool
    {
        $cacheKey = $model->getTable().'.'.$column;

        static $supported = [];

        if (! array_key_exists($cacheKey, $supported)) {
            $supported[$cacheKey] = $model->getConnection()
                ->getSchemaBuilder()
                ->hasColumn($model->getTable(), $column);
        }

        return $supported[$cacheKey];
    }
}