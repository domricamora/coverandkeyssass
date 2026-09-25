<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['billing_invoice_id', 'module_id', 'description', 'quantity', 'unit_cents', 'amount_cents'])]
class InvoiceItem extends Model
{
    protected $table = 'billing_invoice_items';
}
