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

/**
 * Takes a room type — or a single room inside it — off the market for an
 * inclusive date range (maintenance, owner block, private event).
 *
 * The booking engine (Phase 05) must treat blocked rooms as unsellable for
 * every night intersecting [start_date, end_date].
 */
#[Fillable([
    'tenant_id', 'property_id', 'room_type_id', 'room_id', 'start_date',
    'end_date', 'reason', 'note',
])]
class AvailabilityBlock extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const REASON_MAINTENANCE = 'maintenance';

    public const REASON_OWNER_BLOCK = 'owner_block';

    public const REASON_EVENT = 'event';

    public static function reasons(): array
    {
        return [self::REASON_MAINTENANCE, self::REASON_OWNER_BLOCK, self::REASON_EVENT];
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /** Blocks whose inclusive range intersects [from, to]. */
    public function scopeIntersecting(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)->where('end_date', '>=', $from);
    }

    public function scopeForRoomType(Builder $query, int $roomTypeId): Builder
    {
        return $query->where('room_type_id', $roomTypeId);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** True when the block applies to a whole room type instead of one room. */
    public function coversRoomType(): bool
    {
        return $this->room_id === null;
    }

    public function rangeLabel(): string
    {
        return $this->start_date->format('M j, Y').' – '.$this->end_date->format('M j, Y');
    }
}
