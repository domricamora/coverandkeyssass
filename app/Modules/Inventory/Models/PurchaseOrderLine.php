<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'purchase_order_id', 'inventory_item_id', 'quantity', 'received_quantity', 'unit_cost'])]
class PurchaseOrderLine extends Model
{
    use BelongsToTenant;

    protected $attributes = ['received_quantity' => 0];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'received_quantity' => 'decimal:3', 'unit_cost' => 'decimal:4'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function outstanding(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }
}
