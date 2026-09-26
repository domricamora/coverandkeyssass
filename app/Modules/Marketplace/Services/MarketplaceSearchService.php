<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Marketplace\Models\Amenity;
use App\Modules\Marketplace\Models\Cuisine;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\PropertyType;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only marketplace queries.
 *
 * Everything here works off Property::publicQuery() /
 * Restaurant::publicQuery(), i.e. published rows across tenants with the
 * tenant scope dropped ON PURPOSE. No method in this service writes data.
 */
class MarketplaceSearchService
{
    public const PER_PAGE = 12;

    public const SORTS = ['recommended', 'price_asc', 'price_desc', 'rating', 'newest'];

    // ------------------------------------------------------------------
    // Properties
    // ------------------------------------------------------------------

    public function searchProperties(array $filters): LengthAwarePaginator
    {
        $query = Property::publicQuery();

        $this->applyPropertyFilters($query, $filters);
        $this->applyPropertySort($query, (string) ($filters['sort'] ?? 'recommended'));

        $page = $query->paginate(self::PER_PAGE)->withQueryString();

        // With dates, every card shows the real total for the stay and how many rooms are left.
        if (! empty($filters['check_in']) && ! empty($filters['check_out'])) {
            $quotes = app(StayQuoteService::class);
            $page->getCollection()->each(fn (Property $p) => $p->setAttribute(
                'stay',
                $quotes->quote($p, $filters['check_in'], $filters['check_out'], (int) ($filters['guests'] ?? 1)),
            ));
        }

        return $page;
    }

    /** Same filters, scoped to one destination (SEO landing pages). */
    public function searchPropertiesInLocation(Location $location, array $filters): LengthAwarePaginator
    {
        $filters['location'] = $location->slug;

        return $this->searchProperties($filters);
    }

    private function applyPropertyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $term = $this->likeTerm($filters['q']);

            $query->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('region', 'like', $term)
                    ->orWhere('tagline', 'like', $term);
            });
        }

        if (! empty($filters['location'])) {
            $query->whereHas('location', fn (Builder $q) => $q->where('slug', $filters['location']));
        }

        if (! empty($filters['type'])) {
            $query->whereHas('propertyType', fn (Builder $q) => $q->where('slug', $filters['type']));
        }

        if (! empty($filters['guests'])) {
            $query->where('max_guests', '>=', (int) $filters['guests']);
        }

        if (isset($filters['price_min']) && $filters['price_min'] !== null && $filters['price_min'] !== '') {
            $query->where('base_price', '>=', (float) $filters['price_min']);
        }

        if (isset($filters['price_max']) && $filters['price_max'] !== null && $filters['price_max'] !== '') {
            $query->where('base_price', '<=', (float) $filters['price_max']);
        }

        if (! empty($filters['check_in']) && ! empty($filters['check_out'])) {
            $query->whereIn('id', app(StayQuoteService::class)->availablePropertyIds($filters['check_in'], $filters['check_out']));
        }

        if (! empty($filters['free_cancellation'])) {
            $query->whereNotNull('policies->free_cancellation_days');
        }

        if (! empty($filters['min_rating'])) {
            $query->where('avg_rating', '>=', (float) $filters['min_rating']);
        }

        // Amenity filters are conjunctive: a stay must have every amenity asked for.
        foreach ($this->cleanList($filters['amenities'] ?? []) as $slug) {
            $query->whereHas('amenities', fn (Builder $q) => $q->where('slug', $slug));
        }
    }

    private function applyPropertySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('base_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('base_price')->orderBy('id'),
            'rating' => $query->orderByDesc('avg_rating')->orderByDesc('reviews_count')->orderBy('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->ranked(),
        };
    }

    // ------------------------------------------------------------------
    // Restaurants
    // ------------------------------------------------------------------

    public function searchRestaurants(array $filters): LengthAwarePaginator
    {
        $query = Restaurant::publicQuery();

        if (! empty($filters['q'])) {
            $term = $this->likeTerm($filters['q']);

            $query->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('tagline', 'like', $term);
            });
        }

        if (! empty($filters['location'])) {
            $query->whereHas('location', fn (Builder $q) => $q->where('slug', $filters['location']));
        }

        foreach ($this->cleanList($filters['cuisines'] ?? []) as $slug) {
            $query->whereHas('cuisines', fn (Builder $q) => $q->where('slug', $slug));
        }

        if (! empty($filters['price_level'])) {
            $query->where('price_level', '<=', (int) $filters['price_level']);
        }

        match ((string) ($filters['sort'] ?? 'recommended')) {
            'rating' => $query->orderByDesc('avg_rating')->orderByDesc('reviews_count')->orderBy('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy('price_level')->orderBy('id'),
            default => $query->ranked(),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    // ------------------------------------------------------------------
    // Curated selections
    // ------------------------------------------------------------------

    public function featuredProperties(int $limit = 6): Collection
    {
        return Property::publicQuery()
            ->ranked()
            ->limit($limit)
            ->get();
    }

    public function featuredRestaurants(int $limit = 3): Collection
    {
        return Restaurant::publicQuery()
            ->ranked()
            ->limit($limit)
            ->get();
    }

    /** Active property categories (marketplace landing page type grid). */
    public function propertyTypes(): Collection
    {
        return PropertyType::query()->active()->get();
    }

    public function destinations(int $limit = 8): Collection
    {
        return Location::query()
            ->withListings()
            ->orderByDesc('is_featured')
            ->orderByDesc('properties_count')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    /** Other published stays in the same destination (detail page rail). */
    public function similarProperties(Property $property, int $limit = 3): Collection
    {
        return Property::publicQuery()
            ->when($property->location_id, fn (Builder $q) => $q->where('location_id', $property->location_id))
            ->where('id', '!=', $property->getKey())
            ->orderByDesc('avg_rating')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------------------
    // Filter option catalogues + headline stats
    // ------------------------------------------------------------------

    public function filterOptions(): array
    {
        $price = Property::publicQuery()
            ->selectRaw('MIN(base_price) as min_price, MAX(base_price) as max_price')
            ->first();

        return [
            'locations' => Location::query()->withListings()->orderBy('name')->get(),
            'property_types' => PropertyType::query()->active()->get(),
            'amenities' => Amenity::query()->active()->get(),
            'cuisines' => Cuisine::query()->active()->get(),
            'min_price' => (float) ($price->min_price ?? 0),
            'max_price' => (float) ($price->max_price ?? 0),
        ];
    }

    /** Headline numbers for the marketing site (published rows only). */
    public function stats(): array
    {
        return [
            'properties' => Property::publicQuery()->count(),
            'restaurants' => Restaurant::publicQuery()->count(),
            'destinations' => Location::query()->withListings()->count(),
        ];
    }

    /** Escape LIKE wildcards so user input is matched literally. */
    private function likeTerm(string $value): string
    {
        return '%'.addcslashes(trim($value), '%_\\').'%';
    }

    /** @return list<string> */
    private function cleanList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? trim($value) : null,
            $values,
        )));
    }
}