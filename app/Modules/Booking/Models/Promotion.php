<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Promo code: `percent` (value 0–100) or `fixed` amount off the stay
 * subtotal. Never discounts below zero.
 */
#[Fillable([
    'tenant_id', 'property_id', 'code', 'name', 'type', 'value', 'starts_on',
    'ends_on', 'min_nights', 'max_uses', 'is_active', 'applies_to', 'restaurant_id', 'min_subtotal',
])]
class Promotion extends Model
{
    use BelongsToTenant;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    /** Stay codes (Booking) vs food order codes (Ordering, Phase 11). */
    public const FOR_STAYS = 'stays';

    public const FOR_ORDERS = 'orders';

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'min_nights' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'min_subtotal' => 'decimal:2',
        ];
    }

    /** Why this code cannot be used for the stay, or null when it can. */
    public function rejectionFor(int $propertyId, string $checkIn, int $nights): ?string
    {
        return match (true) {
            ! $this->is_active => 'This promo code is no longer active.',
            $this->property_id !== null && (int) $this->property_id !== $propertyId => 'This promo code is not valid at this property.',
            $this->starts_on !== null && $checkIn < $this->starts_on->toDateString(),
            $this->ends_on !== null && $checkIn > $this->ends_on->toDateString() => 'This promo code is not valid for these dates.',
            $nights < $this->min_nights => 'This promo code needs a stay of at least '.$this->min_nights.' nights.',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'This promo code has been fully redeemed.',
            default => null,
        };
    }

    /** Why this code cannot be used for a food order, or null when it can. */
    public function orderRejectionFor(int $restaurantId, float $subtotal): ?string
    {
        $today = today()->toDateString();

        return match (true) {
            $this->applies_to !== self::FOR_ORDERS => 'This promo code is not valid for food orders.',
            ! $this->is_active => 'This promo code is no longer active.',
            $this->restaurant_id !== null && (int) $this->restaurant_id !== $restaurantId => 'This promo code is not valid at this restaurant.',
            $this->starts_on !== null && $today < $this->starts_on->toDateString(),
            $this->ends_on !== null && $today > $this->ends_on->toDateString() => 'This promo code is not valid today.',
            $this->min_subtotal !== null && $subtotal < (float) $this->min_subtotal => 'This promo code needs an order of at least '.number_format((float) $this->min_subtotal, 2).'.',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'This promo code has been fully redeemed.',
            default => null,
        };
    }

    public function discountOn(float $subtotal): float
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? round($subtotal * min(100, (float) $this->value) / 100, 2)
            : (float) $this->value;

        return min($subtotal, $discount);
    }

    public function label(): string
    {
        return $this->type === self::TYPE_PERCENT
            ? (float) $this->value.'% off'
            : number_format((float) $this->value, 2).' off';
    }
}
