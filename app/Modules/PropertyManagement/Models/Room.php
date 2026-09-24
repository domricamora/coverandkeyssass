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
    'status', 'notes',
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

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
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

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function label(): string
    {
        return $this->name ? $this->room_number.' — '.$this->name : $this->room_number;
    }
}
