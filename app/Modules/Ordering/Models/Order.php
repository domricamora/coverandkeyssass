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
 * pending → accepted → preparing → ready ─┬─ (pickup)                 → completed
 *                                         └─ (delivery/room service) → out_for_delivery → delivered → completed
 * pending / accepted → cancelled;  cancelled / completed → refunded (paid online)
 */
#[Fillable([
    'tenant_id', 'restaurant_id', 'user_id', 'booking_id', 'room_id', 'restaurant_table_id', 'promotion_id',
    'reference', 'channel', 'status', 'discount_reason',
    'fulfillment', 'payment_method', 'payment_status', 'customer_name', 'customer_phone',
    'delivery_address', 'delivery_zone_id', 'driver_id', 'delivery_lat', 'delivery_lng',
    'scheduled_for', 'estimated_at', 'notes', 'currency', 'subtotal', 'discount_total', 'tax_rate',
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

    /** Hotel room service (Phase 13): delivered to the room of a checked-in stay. */
    public const ROOM_SERVICE = 'room_service';

    /** Point of sale (Phase 19): eaten at a table, paid at the register. */
    public const DINE_IN = 'dine_in';

    public const CHANNEL_ONLINE = 'online';

    public const CHANNEL_POS = 'pos';

    public const PAY_ONLINE = 'online';

    public const PAY_CASH = 'cash';

    /** Charged to the stay; settled through the guest folio (Phase 14). */
    public const PAY_ROOM = 'room_charge';

    /** Paid at the register, possibly split (see Pos\Models\PosPayment). */
    public const PAY_POS = 'pos';

    public const UNPAID = 'unpaid';

    public const PAID = 'paid';

    public const CHARGED = 'charged';

    /** States the kitchen still has to act on. */
    public const OPEN = [self::PENDING, self::ACCEPTED, self::PREPARING, self::READY, self::OUT_FOR_DELIVERY, self::DELIVERED];

    protected $attributes = ['channel' => self::CHANNEL_ONLINE];

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
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'estimated_at' => 'datetime',
            'delivery_lat' => 'decimal:7',
            'delivery_lng' => 'decimal:7',
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
    public function zone(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Delivery\Models\DeliveryZone::class, 'delivery_zone_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Delivery\Models\Driver::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Booking\Models\Booking::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\RestaurantManagement\Models\RestaurantTable::class, 'restaurant_table_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\PropertyManagement\Models\Room::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return list<string> */
    public function nextStates(): array
    {
        // Refunds: PayMongo for online orders, the register for POS orders.
        $refundable = $this->payment_status === self::PAID && in_array($this->payment_method, [self::PAY_ONLINE, self::PAY_POS], true);

        return match ($this->status) {
            self::PENDING => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY],
            self::READY => [in_array($this->fulfillment, [self::PICKUP, self::DINE_IN], true) ? self::COMPLETED : self::OUT_FOR_DELIVERY],
            self::OUT_FOR_DELIVERY => [self::DELIVERED],
            self::DELIVERED => [self::COMPLETED],
            self::CANCELLED, self::COMPLETED => $refundable ? [self::REFUNDED] : [],
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

    public function fulfillmentLabel(): string
    {
        return Str::headline($this->fulfillment);
    }

    public function paymentLabel(): string
    {
        return match ($this->payment_method) {
            self::PAY_ONLINE => 'Online · '.$this->payment_status,
            self::PAY_ROOM => 'Charged to room',
            self::PAY_POS => 'Register · '.$this->payment_status,
            default => 'Cash',
        };
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }

    public function money(float|string|null $amount): string
    {
        return \App\Support\Currency::format($amount, $this->relationLoaded('tenant') ? $this->tenant : null);
    }
}
