<?php

namespace App\Modules\RestaurantManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A party booked at one table for [reserved_at, ends_at).
 *
 * pending → confirmed → seated → completed
 *    ↘ cancelled   ↘ cancelled / no_show
 */
#[Fillable([
    'tenant_id', 'restaurant_id', 'restaurant_table_id', 'user_id', 'created_by',
    'reference', 'source', 'status', 'reserved_at', 'ends_at', 'party_size',
    'guest_name', 'guest_email', 'guest_phone', 'special_requests',
])]
class TableReservation extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const SEATED = 'seated';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const NO_SHOW = 'no_show';

    public const SOURCE_MARKETPLACE = 'marketplace';

    public const SOURCE_HOST = 'host';

    /** States that hold their table for the time range. */
    public const BLOCKING = [self::PENDING, self::CONFIRMED, self::SEATED];

    public const TRANSITIONS = [
        self::PENDING => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::SEATED, self::CANCELLED, self::NO_SHOW],
        self::SEATED => [self::COMPLETED],
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'ends_at' => 'datetime',
            'party_size' => 'integer',
            'confirmed_at' => 'datetime',
            'seated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function newReference(): string
    {
        do {
            $reference = 'TR'.Str::upper(Str::random(8));
        } while (static::query()->withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    /** A customer's own reservations across businesses; user_id is the boundary. */
    public static function forCustomer(User $user): Builder
    {
        return static::query()->withoutGlobalScope('tenant')->where('user_id', $user->getKey());
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class)->withoutGlobalScope('tenant');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', self::BLOCKING);
    }

    /** Rows whose time range intersects [start, end). */
    public function scopeOverlapping(Builder $query, $start, $end): Builder
    {
        return $query->where('reserved_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function guestCancellable(): bool
    {
        return $this->canTransitionTo(self::CANCELLED) && $this->reserved_at->isFuture();
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }
}
