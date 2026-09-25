<?php

namespace App\Modules\Payments\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One PayMongo checkout attempt for a booking. Status only moves through
 * PaymentService, and `paid` is only ever set from a server-side lookup of
 * the checkout session — never from a browser redirect.
 */
#[Fillable([
    'tenant_id', 'booking_id', 'order_id', 'user_id', 'provider', 'checkout_session_id',
    'payment_intent_id', 'checkout_url', 'amount', 'currency', 'status',
])]
class Payment extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /** A customer's own payments across businesses (tenant scope lifted, user filtered). */
    public static function forCustomer(User $user): Builder
    {
        return static::query()->withoutGlobalScope('tenant')->where('user_id', $user->getKey());
    }

    /** Provider-side lookups from webhooks, which carry no tenant session. */
    public static function byProvider(): Builder
    {
        return static::query()->withoutGlobalScope('tenant');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Set instead of booking_id for food orders (Phase 11). */
    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Ordering\Models\Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /** Amount in centavos, the unit PayMongo uses. */
    public function amountInCentavos(): int
    {
        return (int) round((float) $this->amount * 100);
    }

    public function badge(): string
    {
        return match ($this->status) {
            self::PAID => 'badge-green',
            self::PENDING => 'badge-amber',
            default => 'badge-gray',
        };
    }
}
