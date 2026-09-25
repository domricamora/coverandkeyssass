<?php

namespace App\Modules\Workforce\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A job title ("Room Attendant"), optionally in a department, with a default hourly rate. */
#[Fillable(['tenant_id', 'department_id', 'name', 'hourly_rate'])]
class Position extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:2'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
