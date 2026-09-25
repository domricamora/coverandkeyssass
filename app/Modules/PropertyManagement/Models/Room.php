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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical, rentable room ("101") inside a room type.
 *
 * Status controls sellability: `active` rooms are bookable, `maintenance`
 * rooms are temporarily out of order, `inactive` rooms are retired but
 * kept for history. Tenant isolation via BelongsToTenant.
 */
#[Fillable([
    'tenant_id', 'property_id', 'room_type_id', 'room_number', 'name', 'floor',
    'status', 'notes', 'housekeeping_status',
])]
class Room extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_INACTIVE = 'inactive';

    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_MAINTENANCE, self::STATUS_INACTIVE];
    }

    // Housekeeping status (Phase 15), independent of the sellable `status`.
    public const HK_DIRTY = 'dirty';

    public const HK_CLEANING = 'cleaning';

    public const HK_CLEAN = 'clean';

    public const HK_INSPECTED = 'inspected';

    public const HK_MAINTENANCE = 'maintenance';

    public const HK_OUT_OF_ORDER = 'out_of_order';

    public const HK_STATUSES = [self::HK_DIRTY, self::HK_CLEANING, self::HK_CLEAN, self::HK_INSPECTED, self::HK_MAINTENANCE, self::HK_OUT_OF_ORDER];

    protected $attributes = ['housekeeping_status' => self::HK_CLEAN];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'housekeeping_updated_at' => 'datetime',
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

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /** Active rooms, minus those housekeeping has taken out of order (Phase 15). */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('housekeeping_status', '!=', self::HK_OUT_OF_ORDER);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function label(): string
    {
        return $this->name ? $this->room_number.' — '.$this->name : $this->room_number;
    }
}
