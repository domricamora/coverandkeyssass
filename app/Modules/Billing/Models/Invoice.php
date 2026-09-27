<?php

namespace App\Modules\Billing\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** What a business owes the platform for one period (or a mid-period change). */
#[Fillable(['tenant_id', 'subscription_id', 'number', 'source_key', 'status', 'period_start', 'period_end', 'subtotal_cents', 'discount_cents', 'total_cents', 'currency', 'coupon_code', 'due_at', 'paid_at', 'payment_method', 'payment_reference', 'checkout_session_id', 'checkout_url', 'recorded_by'])]
class Invoice extends Model
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $table = 'billing_invoices';

    protected $attributes = ['status' => self::OPEN, 'currency' => 'PHP'];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime', 'period_end' => 'datetime', 'due_at' => 'datetime', 'paid_at' => 'datetime',
            'subtotal_cents' => 'integer', 'discount_cents' => 'integer', 'total_cents' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'billing_invoice_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public static function money(int $cents): string
    {
        return \App\Support\Currency::platform().number_format($cents / 100, 2);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::OPEN && $this->due_at?->isPast();
    }
}
