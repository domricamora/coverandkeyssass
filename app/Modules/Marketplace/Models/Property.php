<?php

namespace App\Modules\Marketplace\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Concerns\HasMedia;
use App\Modules\Marketplace\Models\Concerns\HasPublicIdentity;
use App\Modules\Marketplace\Observers\PropertyObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned property listing (hotel, resort, B&B, rental…).
 *
 * Tenant isolation: BelongsToTenant applies a deny-by-default global scope,
 * so host-side queries can never read another tenant's rows. The public
 * marketplace deliberately reads across tenants — it uses publicQuery(),
 * which drops the tenant scope and keeps only published rows.
 */
#[Fillable([
    'tenant_id', 'host_id', 'location_id', 'property_type_id', 'slug', 'name', 'tagline',
    'description', 'address_line', 'city', 'region', 'country_code', 'latitude', 'longitude',
    'max_guests', 'bedrooms', 'beds', 'bathrooms', 'base_price', 'weekend_price', 'cleaning_fee',
    'currency', 'check_in_time', 'check_out_time', 'highlights', 'policies', 'status',
    'is_featured', 'published_at',
])]
class Property extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasMedia;
    use HasPublicIdentity;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUSPENDED = 'suspended';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_PUBLISHED,
            self::STATUS_SUSPENDED,
        ];
    }

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'policies' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'base_price' => 'decimal:2',
            'weekend_price' => 'decimal:2',
            'cleaning_fee' => 'decimal:2',
            'avg_rating' => 'decimal:2',
            'max_guests' => 'integer',
            'bedrooms' => 'integer',
            'beds' => 'integer',
            'bathrooms' => 'integer',
            'reviews_count' => 'integer',
            'favorites_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::observe(PropertyObserver::class);
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenity');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable')->latest();
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInLocation(Builder $query, Location|int $location): Builder
    {
        return $query->where('location_id', $location instanceof Location ? $location->id : $location);
    }

    /**
     * Public marketplace entry point: published rows across all tenants.
     *
     * This is the only place the tenant scope is dropped, and it is
     * restricted to non-deleted, published listings. Never use it for
     * host-facing reads or for writes.
     */
    public static function publicQuery(): Builder
    {
        return static::query()
            ->withoutGlobalScope('tenant')
            ->published()
            ->with(['location', 'propertyType', 'media']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function publish(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }

    public function unpublish(): void
    {
        $this->forceFill(['status' => self::STATUS_DRAFT])->save();
    }

    /** "₱8,500" style label for cards and detail pages. */
    public function priceLabel(): string
    {
        return $this->currency.' '.number_format((float) $this->base_price, 0);
    }

    public function locationLabel(): string
    {
        return collect([$this->city, $this->region])->filter()->implode(', ');
    }
}