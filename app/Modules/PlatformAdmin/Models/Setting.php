<?php

namespace App\Modules\PlatformAdmin\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Platform-wide settings edited by the Super Admin; read through a cached map. */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $table = 'platform_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** key => [label, validation rules, help] */
    public const KEYS = [
        'support_email' => ['Support email', ['nullable', 'email', 'max:120'], 'Shown in the site footer and on the contact page.'],
        'support_phone' => ['Support phone', ['nullable', 'string', 'max:40'], 'Shown in the site footer.'],
        'announcement' => ['Site announcement', ['nullable', 'string', 'max:240'], 'A banner across every public page; empty hides it.'],
        'currency_symbol' => ['Currency symbol', ['nullable', 'string', 'max:4'], 'Shown before every amount, e.g. ₱ or $. Businesses can set their own in Business settings. Empty = ₱.'],
        'commission_default_rate' => ['Default commission (%)', ['nullable', 'numeric', 'between:0,50'], 'Used when no commission rule matches a listing. Empty = the .env default.'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('platform_settings', fn () => Schema::hasTable('platform_settings')
            ? static::query()->pluck('value', 'key')->all()
            : []);

        return filled($all[$key] ?? null) ? $all[$key] : $default;
    }

    /** @param  array<string, mixed>  $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }

        Cache::forget('platform_settings');
    }
}
