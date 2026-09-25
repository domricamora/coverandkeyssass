<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Platform discount on a business's subscription (not a guest promotion). */
#[Fillable(['code', 'name', 'percent_off', 'amount_off_cents', 'duration', 'duration_cycles', 'max_redemptions', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    protected $table = 'billing_coupons';

    public const DURATIONS = ['once' => 'First invoice', 'repeating' => 'Several invoices', 'forever' => 'Every invoice'];

    protected $attributes = ['duration' => 'once', 'is_active' => true, 'redeemed_count' => 0];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'is_active' => 'boolean', 'percent_off' => 'integer', 'amount_off_cents' => 'integer', 'duration_cycles' => 'integer', 'max_redemptions' => 'integer', 'redeemed_count' => 'integer'];
    }

    public function usable(): bool
    {
        return $this->is_active
            && (! $this->expires_at || $this->expires_at->isFuture())
            && ($this->max_redemptions === null || $this->redeemed_count < $this->max_redemptions);
    }

    /** How many invoices it discounts; null = every invoice. */
    public function cycles(): ?int
    {
        return match ($this->duration) {
            'once' => 1,
            'repeating' => max(1, (int) $this->duration_cycles),
            default => null,
        };
    }

    public function discountOn(int $cents): int
    {
        $off = $this->percent_off ? intdiv($cents * $this->percent_off, 100) : (int) $this->amount_off_cents;

        return min($cents, $off);
    }

    public function label(): string
    {
        return ($this->percent_off ? $this->percent_off.'% off' : Invoice::money((int) $this->amount_off_cents).' off')
            .' · '.match ($this->duration) {
                'once' => 'first invoice',
                'repeating' => $this->duration_cycles.' invoices',
                default => 'every invoice',
            };
    }
}
