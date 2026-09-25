<?php

namespace App\Modules\Loyalty\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A guest's membership: spendable points, lifetime points (tier), referral code. */
#[Fillable(['tenant_id', 'crm_contact_id', 'referral_code', 'referred_by_id'])]
class LoyaltyAccount extends Model
{
    use BelongsToTenant;

    /** tier => [lifetime points needed, label, perk] */
    public const TIERS = [
        'bronze' => [0, 'Bronze', 'Earn points on every stay and meal'],
        'silver' => [500, 'Silver', 'Priority reservations'],
        'gold' => [2000, 'Gold', 'Late check-out when available'],
        'platinum' => [5000, 'Platinum', 'Room upgrades when available'],
    ];

    protected $attributes = ['points_balance' => 0, 'lifetime_points' => 0, 'tier' => 'bronze'];

    protected function casts(): array
    {
        return ['points_balance' => 'integer', 'lifetime_points' => 'integer'];
    }

    public static function tierFor(int $lifetime): string
    {
        return collect(self::TIERS)->filter(fn ($t) => $lifetime >= $t[0])->keys()->last();
    }

    public static function newReferralCode(string $name): string
    {
        do {
            $code = Str::upper(Str::limit(preg_replace('/[^A-Za-z]/', '', $name) ?: 'GUEST', 5, '')).Str::upper(Str::random(4));
        } while (static::query()->withoutGlobalScopes()->where('tenant_id', app(\App\Support\TenantContext::class)->id())->where('referral_code', $code)->exists());

        return $code;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'crm_contact_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest('id');
    }

    public function tierLabel(): string
    {
        return self::TIERS[$this->tier][1] ?? ucfirst($this->tier);
    }

    /** Points still needed for the next tier, or null at the top. @return array{0: string, 1: int}|null */
    public function nextTier(): ?array
    {
        foreach (self::TIERS as $key => [$needed, $label]) {
            if ($needed > $this->lifetime_points) {
                return [$label, $needed - $this->lifetime_points];
            }
        }

        return null;
    }
}
