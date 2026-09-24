<?php

namespace App\Modules\PropertyManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Modules\Marketplace\Models\Property;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable room category of a property ("Deluxe Room").
 *
 * Pricing here is the default; rate_periods (date ranges) override it for
 * seasons and promos. Tenant isolation via BelongsToTenant; the room type
 * is always reached through its tenant-scoped property on the host side.
 */
#[Fillable([
    'tenant_id', 'property_id', 'name', 'description', 'max_guests', 'beds',
    'bed_configuration', 'size_sqm', 'base_price', 'weekend_price', 'currency',
    'min_stay_nights', 'status', 'sort_order',
])]
class RoomType extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DRAFT = 'draft';

    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_DRAFT];
    }

    protected function casts(): array
    {
        return [
            'max_guests' => 'integer',
            'beds' => 'integer',
            'size_sqm' => 'integer',
            'base_price' => 'decimal:2',
            'weekend_price' => 'decimal:2',
            'min_stay_nights' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function ratePeriods(): HasMany
    {
        return $this->hasMany(RatePeriod::class)->orderBy('start_date');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function priceLabel(): string
    {
        return $this->currency.' '.number_format((float) $this->base_price, 0);
    }
}
