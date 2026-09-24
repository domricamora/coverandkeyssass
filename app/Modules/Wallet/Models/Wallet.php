<?php

namespace App\Modules\Wallet\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A business's host wallet. Balances change only through WalletService (row-locked, ledgered). */
#[Fillable(['tenant_id', 'currency'])]
class Wallet extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('id');
    }

    public function money(string|float|null $amount): string
    {
        return $this->currency.' '.number_format((float) $amount, 2);
    }
}
