<?php

namespace App\Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A chart-of-accounts line. `system_key` marks the accounts automatic postings use. */
#[Fillable(['tenant_id', 'code', 'name', 'type', 'system_key'])]
class LedgerAccount extends Model
{
    use BelongsToTenant;

    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    /** Default chart: system_key => [code, name, type]. */
    public const DEFAULTS = [
        'cash' => ['1000', 'Cash on hand', 'asset'],
        'bank' => ['1010', 'Bank (card, transfer, e-wallet)', 'asset'],
        'platform_wallet' => ['1020', 'Platform wallet (online payments)', 'asset'],
        'guest_receivables' => ['1100', 'Guest receivables (folios)', 'asset'],
        'receivables' => ['1110', 'Accounts receivable (invoices)', 'asset'],
        'inventory' => ['1200', 'Inventory', 'asset'],
        'vat_input' => ['1300', 'Input VAT', 'asset'],
        'payables' => ['2000', 'Accounts payable', 'liability'],
        'vat_output' => ['2100', 'Output VAT', 'liability'],
        'equity' => ['3000', 'Owner equity', 'equity'],
        'room_revenue' => ['4000', 'Room revenue', 'revenue'],
        'fnb_revenue' => ['4100', 'Food & beverage revenue', 'revenue'],
        'other_revenue' => ['4200', 'Other revenue', 'revenue'],
        'cogs' => ['5000', 'Cost of goods sold', 'expense'],
        'waste' => ['5010', 'Waste & shrinkage', 'expense'],
        'commission_expense' => ['6000', 'Platform commissions', 'expense'],
        'maintenance_expense' => ['6100', 'Repairs & maintenance', 'expense'],
        'supplies_expense' => ['6200', 'Operating supplies', 'expense'],
        'general_expense' => ['6900', 'General expenses', 'expense'],
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** Debit-normal accounts grow with debits; the others with credits. */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
