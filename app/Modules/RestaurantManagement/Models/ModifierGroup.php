<?php

namespace App\Modules\RestaurantManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A choice set on a menu item: required modifiers ("Doneness", min 1 max 1)
 * or optional add-ons ("Add-ons", min 0, no max).
 */
#[Fillable(['tenant_id', 'menu_item_id', 'name', 'min_select', 'max_select', 'sort_order'])]
class ModifierGroup extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['min_select' => 'integer', 'max_select' => 'integer', 'sort_order' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function ruleLabel(): string
    {
        return match (true) {
            $this->min_select > 0 && $this->max_select === $this->min_select => "Pick {$this->min_select}",
            $this->min_select > 0 => "Required · min {$this->min_select}".($this->max_select ? " · max {$this->max_select}" : ''),
            $this->max_select !== null => "Optional · up to {$this->max_select}",
            default => 'Optional',
        };
    }
}
