<?php

namespace App\Modules\Delivery\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A rider the business dispatches orders with. Plain records for now —
 * staff accounts arrive with Staff Management (Phase 17).
 */
#[Fillable(['tenant_id', 'name', 'phone', 'vehicle', 'is_active'])]
class Driver extends Model
{
    use BelongsToTenant;

    protected $table = 'delivery_drivers';

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('name');
    }
}
