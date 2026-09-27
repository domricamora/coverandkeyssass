<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A reservation for the stay [check_in, check_out). State changes go
 * through BookingService::transition() only — it owns the state machine
 * and releases inventory; never write `status` directly.
 */
#[Fillable([
    'tenant_id', 'property_id', 'user_id', 'created_by', 'promotion_id', 'source',
    'status', 'group_name', 'check_in', 'check_out', 'adults', 'children',
    'guest_name', 'guest_email', 'guest_phone', 'special_requests', 'currency',
    'subtotal', 'discount_total', 'total', 'hold_expires_at',
])]
class Booking extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const HELD = 'held';

    public const CONFIRMED = 'confirmed';

    public const CHECKED_IN = 'checked_in';

    public const CHECKED_OUT = 'checked_out';

    public const CANCELLED = 'cancelled';

    public const NO_SHOW = 'no_show';

    public const REFUNDED = 'refunded';

    public const COMPLETED = 'completed';

    /** Allowed state machine edges: from => [to, ...]. */
    public const TRANSITIONS = [
        self::PENDING => [self::HELD, self::CONFIRMED, self::CANCELLED],
        self::HELD => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::CHECKED_IN, self::CANCELLED, self::NO_SHOW],
        self::CHECKED_IN => [self::CHECKED_OUT],
        self::CHECKED_OUT => [self::COMPLETED],
        self::CANCELLED => [self::REFUNDED],
        self::NO_SHOW => [self::REFUNDED],
        self::REFUNDED => [],
        self::COMPLETED => [],
    ];

    /** States in which the booking still occupies its rooms. */
    public const OCCUPYING = [self::PENDING, self::HELD, self::CONFIRMED, self::CHECKED_IN];

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_MARKETPLACE = 'marketplace';

    public static function statuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->reference ??= self::newReference();
        });
    }

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function newReference(): string
    {
        do {
            $reference = 'BK'.Str::upper(Str::random(8));
        } while (static::query()->withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * A customer's own bookings across every business. The tenant scope is
     * lifted deliberately; the user_id filter is the isolation boundary.
     */
    public static function forCustomer(User $user): Builder
    {
        return static::query()->withoutGlobalScope('tenant')->where('user_id', $user->getKey());
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isUpcoming(): bool
    {
        return in_array($this->status, [self::PENDING, self::HELD, self::CONFIRMED], true)
            && $this->check_in->greaterThanOrEqualTo(today());
    }

    /** Guests may cancel themselves only before the check-in date. */
    public function guestCancellable(): bool
    {
        return in_array($this->status, [self::PENDING, self::HELD, self::CONFIRMED], true)
            && $this->check_in->isAfter(today());
    }

    /** A finished stay the guest can review. */
    public function reviewable(): bool
    {
        return in_array($this->status, [self::CHECKED_OUT, self::COMPLETED], true);
    }

    public function badge(): string
    {
        return match ($this->status) {
            self::CONFIRMED, self::CHECKED_IN, self::COMPLETED => 'badge-green',
            self::PENDING, self::HELD => 'badge-amber',
            default => 'badge-gray',
        };
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }

    public function money(string|float|null $amount): string
    {
        return \App\Support\Currency::format($amount, $this->relationLoaded('tenant') ? $this->tenant : null);
    }
}
