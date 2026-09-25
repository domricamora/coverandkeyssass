<?php

namespace App\Modules\Billing\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One per business: the modules it pays for, how often, and the current period. */
#[Fillable(['tenant_id', 'status', 'billing_interval', 'current_period_start', 'current_period_end', 'cancel_at_period_end', 'cancelled_at', 'billing_coupon_id', 'coupon_cycles_used'])]
class Subscription extends Model
{
    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const CANCELLED = 'cancelled';

    public const INTERVALS = ['monthly' => 'Monthly', 'yearly' => 'Yearly'];

    protected $attributes = ['status' => self::ACTIVE, 'billing_interval' => 'monthly', 'cancel_at_period_end' => false, 'coupon_cycles_used' => 0];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'coupon_cycles_used' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'billing_coupon_id');
    }
}
