<?php

namespace App\Modules\Wallet\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Append-only wallet ledger row (signed amount in one bucket). */
#[Fillable([
    'tenant_id', 'wallet_id', 'type', 'bucket', 'amount', 'balance_after',
    'commission_id', 'payout_id', 'description',
])]
class WalletTransaction extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }
}
