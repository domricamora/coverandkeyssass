<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** "Meat", "Dry goods", "Amenities", "Linen". */
#[Fillable(['tenant_id', 'name'])]
class InventoryCategory extends Model
{
    use BelongsToTenant;
}
