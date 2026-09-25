<?php

namespace App\Modules\Wallet\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Booking\Models\Booking;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform / host split of one paid payment. `pending` until the stay is
 * checked out (or no-show), then `released` to the available balance;
 * `reversed` when the payment is refunded.
 */
#[Fillable([
    'tenant_id', 'booking_id', 'order_id', 'payment_id', 'commission_rate_id', 'gross', 'rate',
    'platform_fee', 'host_amount', 'status',
])]
class Commission extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const RELEASED = 'released';

    public const REVERSED = 'reversed';

    protected function casts(): array
    {
        return [
            'gross' => 'decimal:2',
            'rate' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'host_amount' => 'decimal:2',
            'released_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Ordering\Models\Order::class);
    }

    /** "Booking BK…" / "Order OR…" for ledger and admin screens. */
    public function sourceLabel(): string
    {
        return $this->order_id ? 'Order '.$this->order?->reference : 'Booking '.$this->booking?->reference;
    }
}
