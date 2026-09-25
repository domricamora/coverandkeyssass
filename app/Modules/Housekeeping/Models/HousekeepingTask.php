<?php

namespace App\Modules\Housekeeping\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\Room;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A cleaning job for one room. pending → in_progress → completed, then an
 * inspection passes (room inspected) or fails (room dirty + a re-clean).
 */
#[Fillable([
    'tenant_id', 'property_id', 'room_id', 'booking_id', 'type', 'status', 'priority',
    'due_on', 'notes', 'assigned_to', 'created_by',
])]
class HousekeepingTask extends Model
{
    use BelongsToTenant;

    public const TYPES = ['checkout_clean', 'stayover', 'deep_clean', 'turndown'];

    public const PENDING = 'pending';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected $attributes = ['status' => self::PENDING, 'priority' => 'normal'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'inspected_at' => 'datetime',
            'inspection_passed' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PENDING, self::IN_PROGRESS]);
    }

    /** Completed but not yet inspected. */
    public function scopeAwaitingInspection(Builder $query): Builder
    {
        return $query->where('status', self::COMPLETED)->whereNull('inspected_at');
    }

    public function typeLabel(): string
    {
        return Str::headline($this->type);
    }
}
