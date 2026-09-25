<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Platform commission rate (percent), set by the Super Admin.
 *
 * Resolution for a listing on a date (first match wins):
 *   promotional rate for that listing → promotional rate for everyone →
 *   listing rate (property / restaurant) → global rate →
 *   config('services.commission.default_rate').
 */
#[Fillable(['kind', 'rateable_type', 'rateable_id', 'rate', 'name', 'starts_on', 'ends_on'])]
class CommissionRate extends Model
{
    public const GLOBAL = 'global';

    public const LISTING = 'listing';

    public const PROMOTIONAL = 'promotional';

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function rateable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The rate row that applies, or null when only the config default applies. */
    public static function resolveFor(Model $listing, string $date): ?self
    {
        $active = fn (Builder $q) => $q
            ->where(fn ($w) => $w->whereNull('starts_on')->orWhere('starts_on', '<=', $date))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $date));
        $forListing = fn (Builder $q) => $q->where('rateable_type', $listing->getMorphClass())->where('rateable_id', $listing->getKey());

        return static::query()->where('kind', self::PROMOTIONAL)->tap($active)->tap($forListing)->latest('id')->first()
            ?? static::query()->where('kind', self::PROMOTIONAL)->tap($active)->whereNull('rateable_id')->latest('id')->first()
            ?? static::query()->where('kind', self::LISTING)->tap($forListing)->latest('id')->first()
            ?? static::query()->where('kind', self::GLOBAL)->latest('id')->first();
    }

    public static function defaultRate(): float
    {
        return (float) \App\Modules\PlatformAdmin\Models\Setting::get('commission_default_rate', config('services.commission.default_rate', 10));
    }
}
