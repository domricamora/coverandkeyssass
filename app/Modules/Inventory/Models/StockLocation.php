<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A place stock is kept: "Main store", "Kitchen", "Housekeeping closet". */
#[Fillable(['tenant_id', 'name', 'property_id'])]
class StockLocation extends Model
{
    use BelongsToTenant;
}
