<?php

namespace App\Modules\Delivery\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a restaurant delivers and on what terms. A zone with `radius_km`
 * is a circle around the restaurant's coordinates; without one it is a
 * named area ("Station 2") the customer picks.
 */
#[Fillable([
    'tenant_id', 'restaurant_id', 'name', 'radius_km', 'fee', 'min_order',
    'free_over', 'eta_minutes', 'is_active', 'sort_order',
])]
class DeliveryZone extends Model
{
    use BelongsToTenant;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'radius_km' => 'decimal:2',
            'fee' => 'decimal:2',
            'min_order' => 'decimal:2',
            'free_over' => 'decimal:2',
            'eta_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /** Fee for an order of $amount (after discounts): free above the threshold. */
    public function feeFor(float $amount): float
    {
        return $this->free_over !== null && $amount >= (float) $this->free_over ? 0.0 : (float) $this->fee;
    }

    /** Great-circle distance in km (haversine). */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function covers(?float $lat, ?float $lng, Restaurant $restaurant): bool
    {
        if ($this->radius_km === null) {
            return true;
        }

        if ($lat === null || $lng === null || $restaurant->latitude === null || $restaurant->longitude === null) {
            return false;
        }

        return self::distanceKm((float) $restaurant->latitude, (float) $restaurant->longitude, $lat, $lng) <= (float) $this->radius_km;
    }

    public function termsLabel(): string
    {
        return collect([
            (float) $this->fee > 0 ? \App\Support\Currency::symbol().number_format((float) $this->fee, 0).' fee' : 'free delivery',
            $this->free_over !== null ? 'free over '.\App\Support\Currency::symbol().number_format((float) $this->free_over, 0) : null,
            $this->min_order !== null ? 'min '.\App\Support\Currency::symbol().number_format((float) $this->min_order, 0) : null,
            $this->radius_km !== null ? 'within '.(float) $this->radius_km.' km' : null,
            '~'.$this->eta_minutes.' min',
        ])->filter()->implode(' · ');
    }
}
