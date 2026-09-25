<?php

namespace App\Modules\Billing\Models;

use App\Models\Module;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subscription_id', 'module_id', 'quantity'])]
class SubscriptionItem extends Model
{
    protected $attributes = ['quantity' => 1];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
