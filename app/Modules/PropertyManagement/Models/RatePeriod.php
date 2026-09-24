<?php

namespace App\Modules\PropertyManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Date-range pricing override for a room type (high season, holidays,
 * promos). Overlapping periods for the same room type are rejected by the
 * application so every night resolves to exactly one rate.
 */
#[Fillable([
    'tenant_id', 'room_type_id', 'name', 'start_date', 'end_date',
    'nightly_price', 'weekend_nightly_price', 'min_stay_nights',
])]
class RatePeriod extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'nightly_price' => 'decimal:2',
            'weekend_nightly_price' => 'decimal:2',
            'min_stay_nights' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /** Periods whose inclusive range intersects [from, to]. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)->where('end_date', '>=', $from);
    }

    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)->where('end_date', '>=', $date);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function rangeLabel(): string
    {
        return $this->start_date->format('M j, Y').' – '.$this->end_date->format('M j, Y');
    }
}
