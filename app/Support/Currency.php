<?php

namespace App\Support;

use App\Models\Tenant;
use App\Modules\PlatformAdmin\Models\Setting;

/**
 * Money display: a symbol, never an ISO code ("₱1,250.00", not "PHP 1,250.00").
 * The business's own symbol (tenants.settings.currency_symbol) wins, then the
 * platform default (Super Admin → Settings), then ₱.
 */
class Currency
{
    public const FALLBACK = '₱';

    public static function platform(): string
    {
        return (string) Setting::get('currency_symbol', self::FALLBACK);
    }

    /** $tenant null = the active business, if any. */
    public static function symbol(?Tenant $tenant = null): string
    {
        $tenant ??= app(TenantContext::class)->has() ? app(TenantContext::class)->tenant() : null;

        return filled($tenant?->settings['currency_symbol'] ?? null) ? $tenant->settings['currency_symbol'] : self::platform();
    }

    public static function format(float|int|string|null $amount, ?Tenant $tenant = null, int $decimals = 2): string
    {
        $amount = (float) $amount;

        return ($amount < 0 ? '-' : '').self::symbol($tenant).number_format(abs($amount), $decimals);
    }
}
