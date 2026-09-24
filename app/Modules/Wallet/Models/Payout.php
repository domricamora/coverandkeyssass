<?php

namespace App\Modules\Wallet\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Host withdrawal. The amount leaves the available balance on request;
 * the Super Admin marks it paid after transferring, or rejects it (the
 * amount is credited back).
 */
#[Fillable([
    'tenant_id', 'wallet_id', 'amount', 'status', 'method', 'account_name',
    'account_number', 'requested_by',
])]
class Payout extends Model
{
    use BelongsToTenant;

    public const REQUESTED = 'requested';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const METHODS = ['bank', 'gcash', 'maya'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function badge(): string
    {
        return match ($this->status) {
            self::PAID => 'badge-green',
            self::REQUESTED => 'badge-amber',
            default => 'badge-gray',
        };
    }
}
