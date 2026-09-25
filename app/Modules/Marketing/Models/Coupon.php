<?php

namespace App\Modules\Marketing\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Booking\Models\Promotion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A personal, single-use code that redeems its promotion's discount.
 * Shared discount codes are plain promotions; coupons are one per guest.
 */
#[Fillable(['tenant_id', 'promotion_id', 'crm_contact_id', 'code'])]
class Coupon extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public static function newCode(Promotion $promotion): string
    {
        do {
            $code = Str::upper(Str::limit($promotion->code, 12, '')).'-'.Str::upper(Str::random(6));
        } while (static::query()->where('code', $code)->exists() || Promotion::query()->where('code', $code)->exists());

        return $code;
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
