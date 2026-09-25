<?php

namespace App\Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'invoice_id', 'paid_on', 'amount', 'method', 'reference'])]
class InvoicePayment extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2'];
    }
}
