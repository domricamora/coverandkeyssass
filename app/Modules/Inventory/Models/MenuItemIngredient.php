<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\RestaurantManagement\Models\MenuItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One recipe line: selling one menu item uses `quantity` (item unit) of the stock item. */
#[Fillable(['tenant_id', 'menu_item_id', 'inventory_item_id', 'quantity', 'entered_unit', 'entered_quantity'])]
class MenuItemIngredient extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'entered_quantity' => 'decimal:3'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
