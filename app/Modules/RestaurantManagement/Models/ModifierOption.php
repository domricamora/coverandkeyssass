<?php

namespace App\Modules\RestaurantManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One choice in a modifier group ("Cheese +₱30"). */
#[Fillable(['tenant_id', 'modifier_group_id', 'name', 'price', 'is_available', 'sort_order'])]
class ModifierOption extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_available' => 'boolean', 'sort_order' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
