<?php

namespace App\Modules\Loyalty\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Booking\Models\Promotion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something points buy: a personal coupon (from a promotion) or store credit. */
#[Fillable(['tenant_id', 'name', 'points_cost', 'kind', 'promotion_id', 'credit_amount', 'is_active'])]
class Reward extends Model
{
    use BelongsToTenant;

    protected $table = 'loyalty_rewards';

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['points_cost' => 'integer', 'credit_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
