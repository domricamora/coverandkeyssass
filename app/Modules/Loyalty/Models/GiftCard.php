<?php

namespace App\Modules\Loyalty\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Prepaid balance spendable at the POS and on guest folios. `gift` cards
 * are sold; `credit` cards are a guest's store credit (loyalty rewards,
 * goodwill) — one mechanism for both.
 */
#[Fillable(['tenant_id', 'code', 'kind', 'crm_contact_id', 'initial_value', 'balance', 'sold_via', 'expires_on', 'status', 'issued_by'])]
class GiftCard extends Model
{
    use BelongsToTenant;

    protected $attributes = ['kind' => 'gift', 'status' => 'active'];

    protected function casts(): array
    {
        return ['initial_value' => 'decimal:2', 'balance' => 'decimal:2', 'expires_on' => 'date'];
    }

    public static function newCode(): string
    {
        do {
            $code = 'GC-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'crm_contact_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class)->latest('id');
    }

    public function usable(): bool
    {
        return $this->status === 'active' && (float) $this->balance > 0 && (! $this->expires_on || ! $this->expires_on->isPast());
    }
}
