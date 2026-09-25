<?php

namespace App\Modules\Loyalty\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One programme per business: earn rate and referral bonus (off until enabled). */
#[Fillable(['tenant_id', 'enabled', 'pesos_per_point', 'referral_points'])]
class LoyaltyProgram extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'pesos_per_point' => 'decimal:2', 'referral_points' => 'integer'];
    }

    public function pointsFor(float $amount): int
    {
        return (int) floor($amount / max(1, (float) $this->pesos_per_point));
    }
}
