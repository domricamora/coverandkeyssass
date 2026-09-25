<?php

namespace App\Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A bill paid (or owed) outside purchasing: utilities, rent, fuel… */
#[Fillable(['tenant_id', 'ledger_account_id', 'vendor', 'expense_date', 'amount', 'tax_amount', 'paid_from', 'reference', 'notes', 'created_by'])]
class Expense extends Model
{
    use BelongsToTenant;

    public const PAID_FROM = ['cash', 'bank', 'payable'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'tax_amount' => 'decimal:2'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
