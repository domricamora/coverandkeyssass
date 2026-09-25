<?php

namespace App\Modules\Workforce\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** "Front Office", "Housekeeping", "Kitchen", "Engineering". */
#[Fillable(['tenant_id', 'name', 'description'])]
class Department extends Model
{
    use BelongsToTenant;

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
