<?php

namespace App\Modules\Marketplace\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Public identity for marketplace listings: a stable UUID reference
 * (shareable/exportable without exposing auto-increment ids) and a
 * unique, SEO-friendly slug derived from the name.
 *
 * The UUID is opt-out because platform reference tables (locations,
 * property types, amenities, cuisines) identify their rows by slug and
 * carry no uuid column.
 */
trait HasPublicIdentity
{
    public static function bootHasPublicIdentity(): void
    {
        static::creating(function ($model): void {
            if ($model->publicIdentityHasUuid() && ! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }

            if (! $model->slug) {
                $model->slug = static::uniqueSlug($model->name ?? 'listing');
            }
        });
    }

    /** Reference data without a uuid column overrides this to false. */
    public function publicIdentityHasUuid(): bool
    {
        return true;
    }

    /** Slug candidate that is free in this table (adds -2, -3… when taken). */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'listing';
        $slug = $base;
        $suffix = 2;

        while (static::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}