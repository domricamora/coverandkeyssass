<?php

namespace App\Modules\Marketplace\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Concerns\HasMedia;
use App\Modules\Marketplace\Models\Concerns\HasPublicIdentity;
use App\Modules\Marketplace\Observers\RestaurantObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant-owned restaurant listing. Menus, tables and orders arrive in
 * Phases 09–11; this is the marketplace directory entry.
 */
#[Fillable([
    'tenant_id', 'host_id', 'location_id', 'slug', 'name', 'tagline', 'description',
    'address_line', 'city', 'region', 'country_code', 'latitude', 'longitude',
    'phone', 'email', 'price_level', 'opening_hours', 'highlights',
    'reservations_enabled', 'delivery_enabled', 'status', 'is_featured', 'published_at',
])]
class Restaurant extends Model
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

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'highlights' => 'array',
            'reservations_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'avg_rating' => 'decimal:2',
            'price_level' => 'integer',
            'reviews_count' => 'integer',
            'favorites_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::observe(RestaurantObserver::class);
    }

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

    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class, 'cuisine_restaurant');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable')->latest();
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    // Restaurant Management (Phase 09) — menu and floor plan

    public function menuCategories(): HasMany
    {
        return $this->hasMany(\App\Modules\RestaurantManagement\Models\MenuCategory::class)->orderBy('sort_order')->orderBy('name');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(\App\Modules\RestaurantManagement\Models\MenuItem::class);
    }

    public function diningAreas(): HasMany
    {
        return $this->hasMany(\App\Modules\RestaurantManagement\Models\DiningArea::class)->orderBy('sort_order')->orderBy('name');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(\App\Modules\RestaurantManagement\Models\RestaurantTable::class)->orderBy('label');
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** See Property::publicQuery() — the single tenant-scope bypass. */
    public static function publicQuery(): Builder
    {
        return static::query()
            ->withoutGlobalScope('tenant')
            ->published()
            ->with(['location', 'cuisines', 'media']);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function locationLabel(): string
    {
        return collect([$this->city, $this->region])->filter()->implode(', ');
    }

    /** "₱₱" style price level label. */
    public function priceLevelLabel(): string
    {
        $level = max(1, min(4, (int) $this->price_level));

        return str_repeat('₱', $level);
    }
}