<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Marketplace\Models\Concerns\HasPublicIdentity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A destination (city/island) that listings are grouped under.
 * Platform reference data — not tenant-owned.
 */
#[Fillable([
    'name', 'slug', 'region', 'country_code', 'country', 'latitude', 'longitude',
    'description', 'is_featured', 'sort_order',
])]
class Location extends Model
{
    use HasFactory;
    use HasPublicIdentity;

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'properties_count' => 'integer',
            'restaurants_count' => 'integer',
        ];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /** Destinations that actually have something to browse. */
    public function scopeWithListings($query)
    {
        return $query->where(function ($q) {
            $q->where('properties_count', '>', 0)->orWhere('restaurants_count', '>', 0);
        });
    }

    /** Destinations are identified by slug — the table has no uuid column. */
    public function publicIdentityHasUuid(): bool
    {
        return false;
    }
}