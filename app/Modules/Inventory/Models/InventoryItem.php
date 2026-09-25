<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A stock-keeping unit: "Beef patty mince" in kg, "Burger bun" in pc. */
#[Fillable(['tenant_id', 'inventory_category_id', 'sku', 'name', 'unit', 'cost_per_unit', 'reorder_level', 'is_active'])]
class InventoryItem extends Model
{
    use BelongsToTenant;

    protected $attributes = ['is_active' => true, 'cost_per_unit' => 0, 'reorder_level' => 0];

    protected function casts(): array
    {
        return ['cost_per_unit' => 'decimal:4', 'reorder_level' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /** Items whose total stock is at or below their reorder level. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('reorder_level', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.inventory_item_id = inventory_items.id) <= inventory_items.reorder_level');
    }

    public function totalStock(): float
    {
        return (float) ($this->relationLoaded('levels') ? $this->levels->sum('quantity') : $this->levels()->sum('quantity'));
    }

    public function qty(float|string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 3, '.', ','), '0'), '.').' '.$this->unit;
    }
}
