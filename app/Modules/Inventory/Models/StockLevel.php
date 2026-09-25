<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** On-hand quantity of one item at one location; only InventoryService changes it. */
#[Fillable(['tenant_id', 'inventory_item_id', 'stock_location_id', 'quantity'])]
class StockLevel extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
