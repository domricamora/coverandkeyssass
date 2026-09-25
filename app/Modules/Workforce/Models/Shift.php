<?php

namespace App\Modules\Workforce\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Marketplace\Models\Property;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A scheduled working period [starts_at, ends_at) on the roster. */
#[Fillable(['tenant_id', 'employee_id', 'property_id', 'starts_at', 'ends_at', 'status', 'notes', 'created_by'])]
class Shift extends Model
{
    use BelongsToTenant;

    public const SCHEDULED = 'scheduled';

    public const CANCELLED = 'cancelled';

    protected $attributes = ['status' => self::SCHEDULED];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::SCHEDULED);
    }

    public function scopeOverlapping(Builder $query, $start, $end): Builder
    {
        return $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function label(): string
    {
        return $this->starts_at->format('g:i A').'–'.$this->ends_at->format('g:i A');
    }
}
