<?php

namespace App\Modules\Loyalty\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only points ledger line with the balance after it. */
#[Fillable(['tenant_id', 'loyalty_account_id', 'type', 'points', 'balance_after', 'description', 'source_key', 'user_id'])]
class LoyaltyTransaction extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['points' => 'integer', 'balance_after' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
