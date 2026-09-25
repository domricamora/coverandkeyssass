<?php

namespace App\Modules\Crm\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Built-in guest segments (master plan Phase 21), as query filters over
 * the cached contact metrics. Marketing (Phase 22) targets these.
 */
final class Segments
{
    // ponytail: fixed thresholds; make them business settings when owners ask.
    public const HIGH_SPENDER = 20000;

    public const FREQUENT_STAYS = 3;

    public const INACTIVE_DAYS = 180;

    public const NEW_DAYS = 30;

    /** @return array<string, string> key => label */
    public static function all(): array
    {
        return [
            'vip' => 'VIP',
            'frequent_guest' => 'Frequent Guest',
            'inactive' => 'Inactive',
            'high_spender' => 'High Spender',
            'new_customer' => 'New Customer',
            'restaurant_customer' => 'Restaurant Customer',
            'hotel_customer' => 'Hotel Customer',
        ];
    }

    public static function apply(Builder $query, string $segment): Builder
    {
        return match ($segment) {
            'vip' => $query->where('is_vip', true),
            'frequent_guest' => $query->where('bookings_count', '>=', self::FREQUENT_STAYS),
            'inactive' => $query->where('last_activity_at', '<', now()->subDays(self::INACTIVE_DAYS)),
            'high_spender' => $query->where('total_spend', '>=', self::HIGH_SPENDER),
            'new_customer' => $query->where('first_seen_at', '>=', now()->subDays(self::NEW_DAYS)),
            'restaurant_customer' => $query->whereRaw('orders_count + reservations_count > 0'),
            'hotel_customer' => $query->where('bookings_count', '>', 0),
            default => $query,
        };
    }
}
