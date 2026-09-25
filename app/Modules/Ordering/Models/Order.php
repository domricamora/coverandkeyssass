<?php

namespace App\Modules\Ordering\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A food order (Phase 11). Status only moves through OrderService.
 *
 * pending → accepted → preparing → ready ─┬─ (pickup)   → completed
 *                                         └─ (delivery) → out_for_delivery → delivered → completed
 * pending / accepted → cancelled;  cancelled / completed → refunded (paid online)
 */
#[Fillable([
    'tenant_id', 'restaurant_id', 'user_id', 'promotion_id', 'reference', 'status',
    'fulfillment', 'payment_method', 'payment_status', 'customer_name', 'customer_phone',
    'delivery_address', 'notes', 'currency', 'subtotal', 'discount_total', 'tax_rate',
    'tax_inclusive', 'tax_total', 'delivery_fee', 'total',
])]
class Order extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const PREPARING = 'preparing';

    public const READY = 'ready';

    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public const DELIVERED = 'delivered';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const PICKUP = 'pickup';

    public const DELIVERY = 'delivery';

    public const PAY_ONLINE = 'online';

    public const PAY_CASH = 'cash';

    public const UNPAID = 'unpaid';

    public const PAID = 'paid';

    /** States the kitchen still has to act on. */
    public const OPEN = [self::PENDING, self::ACCEPTED, self::PREPARING, self::READY, self::OUT_FOR_DELIVERY, self::DELIVERED];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'tax_total' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function newReference(): string
    {
        do {
            $reference = 'OR'.Str::upper(Str::random(8));
        } while (static::query()->withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    /** A customer's own orders across businesses; user_id is the boundary. */
    public static function forCustomer(User $user): Builder
    {
        return static::query()->withoutGlobalScope('tenant')->where('user_id', $user->getKey());
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class)->withoutGlobalScope('tenant');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return list<string> */
    public function nextStates(): array
    {
        $paidOnline = $this->payment_method === self::PAY_ONLINE && $this->payment_status === self::PAID;

        return match ($this->status) {
            self::PENDING => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY],
            self::READY => [$this->fulfillment === self::DELIVERY ? self::OUT_FOR_DELIVERY : self::COMPLETED],
            self::OUT_FOR_DELIVERY => [self::DELIVERED],
            self::DELIVERED => [self::COMPLETED],
            self::CANCELLED, self::COMPLETED => $paidOnline ? [self::REFUNDED] : [],
            default => [],
        };
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, $this->nextStates(), true);
    }

    public function needsPayment(): bool
    {
        return $this->payment_method === self::PAY_ONLINE && $this->payment_status === self::UNPAID && $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }

    public function money(float|string|null $amount): string
    {
        return ($this->currency === 'PHP' ? '₱' : $this->currency.' ').number_format((float) $amount, 2);
    }
}
