<?php

namespace App\Modules\Marketplace\Models\Concerns;

use App\Modules\Marketplace\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gallery support for a listing: ordered media plus a single cover.
 *
 * The cover is the flagged `is_cover` row, falling back to the first
 * gallery item so a listing published without an explicit cover still
 * renders something meaningful.
 */
trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->ordered();
    }

    public function coverMedia(): ?Media
    {
        if ($this->relationLoaded('media')) {
            return $this->media->firstWhere('is_cover', true) ?? $this->media->first();
        }

        return $this->media()->first();
    }

    public function coverUrl(): ?string
    {
        return $this->coverMedia()?->url();
    }

    /** Gallery images, cover first, de-duplicated. */
    public function galleryUrls(): array
    {
        $media = $this->relationLoaded('media') ? $this->media : $this->media()->get();

        return $media->map(fn (Media $item) => $item->url())->unique()->values()->all();
    }
}