<?php

namespace App\Modules\Workforce\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Time off for [starts_on, ends_on] (inclusive); pending until a manager decides. */
#[Fillable(['tenant_id', 'employee_id', 'type', 'starts_on', 'ends_on', 'reason', 'status'])]
class LeaveRequest extends Model
{
    use BelongsToTenant;

    public const TYPES = ['vacation', 'sick', 'emergency', 'unpaid'];

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    protected $attributes = ['status' => self::PENDING];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'decided_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Inclusive date ranges that intersect [from, to]. */
    public function scopeCovering(Builder $query, string $from, string $to): Builder
    {
        return $query->where('starts_on', '<=', $to)->where('ends_on', '>=', $from);
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->ends_on) + 1;
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }
}
