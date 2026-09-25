<?php

namespace App\Modules\Workforce\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One clock-in / clock-out, matched to the shift it covers (if any). */
#[Fillable(['tenant_id', 'employee_id', 'shift_id', 'clock_in_at', 'clock_out_at', 'minutes_worked', 'late_minutes', 'notes', 'recorded_by'])]
class Attendance extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'minutes_worked' => 'integer',
            'late_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function hoursLabel(): string
    {
        return $this->minutes_worked === null ? 'on shift' : intdiv($this->minutes_worked, 60).'h '.str_pad((string) ($this->minutes_worked % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
