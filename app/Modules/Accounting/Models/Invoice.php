<?php

namespace App\Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer invoice (events, corporate accounts): draft → issued → paid, or void. */
#[Fillable(['tenant_id', 'number', 'customer_name', 'customer_email', 'issue_date', 'due_date', 'status', 'tax_rate', 'notes', 'created_by'])]
class Invoice extends Model
{
    use BelongsToTenant;

    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $attributes = ['status' => self::DRAFT];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public static function nextNumber(): string
    {
        $next = (int) static::query()->count() + 1;

        do {
            $number = 'INV-'.str_pad((string) $next++, 5, '0', STR_PAD_LEFT);
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_on');
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', self::ISSUED);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::ISSUED && $this->due_date->isPast();
    }
}
