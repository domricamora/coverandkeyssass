<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'name', 'contact_name', 'phone', 'email'])]
class Supplier extends Model
{
    use BelongsToTenant;
}
