<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A cashier shift at one restaurant's register: opening float → sales → counted cash. */
#[Fillable(['tenant_id', 'restaurant_id', 'opened_by', 'opening_float', 'opened_at'])]
class PosSession extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** Float plus net cash taken (cash refunds are negative rows). */
    public function cashInDrawer(): float
    {
        return round((float) $this->opening_float + (float) $this->payments()->where('method', 'cash')->sum('amount'), 2);
    }
}
