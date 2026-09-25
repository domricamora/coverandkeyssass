<?php

namespace App\Modules\Marketplace\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Marketplace placement (Phase 29), shared by properties and restaurants:
 * featured (optionally until a date), sponsored (paid, until a date,
 * always labelled), a Super Admin ranking boost and verification.
 */
trait Promotable
{
    public function initializePromotable(): void
    {
        $this->mergeCasts(['featured_until' => 'datetime', 'sponsored_until' => 'datetime', 'verified_at' => 'datetime', 'ranking_boost' => 'integer']);
    }

    public function isFeaturedNow(): bool
    {
        return $this->is_featured && (! $this->featured_until || $this->featured_until->isFuture());
    }

    public function isSponsored(): bool
    {
        return (bool) $this->sponsored_until?->isFuture();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * "Recommended" order: live sponsorships, then live features, then a
     * score — rating (×10), review volume (capped), verification and the
     * Super Admin boost.
     */
    public function scopeRanked(Builder $query): Builder
    {
        $now = now()->toDateTimeString();
        $table = $this->getTable();

        return $query
            ->orderByRaw("({$table}.sponsored_until IS NOT NULL AND {$table}.sponsored_until > ?) DESC", [$now])
            ->orderByRaw("({$table}.is_featured = 1 AND ({$table}.featured_until IS NULL OR {$table}.featured_until > ?)) DESC", [$now])
            ->orderByRaw("({$table}.ranking_boost + {$table}.avg_rating * 10 + LEAST({$table}.reviews_count, 50) * 0.2 + ({$table}.verified_at IS NOT NULL) * 5) DESC")
            ->orderBy("{$table}.id");
    }
}
