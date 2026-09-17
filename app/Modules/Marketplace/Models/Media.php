<?php

namespace App\Modules\Marketplace\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Polymorphic media row (cover, gallery, photo…).
 *
 * Owned by its parent listing: it carries no tenant scope of its own and
 * must only be reached through a parent that has already been
 * tenant-scoped (host side) or publication-filtered (public side).
 */
#[Fillable([
    'mediable_type', 'mediable_id', 'disk', 'path', 'alt', 'caption',
    'is_cover', 'sort_order', 'width', 'height',
])]
class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Public URL for the file.
     *
     * - absolute URLs are passed through (external CDN/seed data),
     * - paths that exist under /public are served directly (bundled assets),
     * - everything else resolves through the configured disk (uploads).
     */
    public function url(): string
    {
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        if (file_exists(public_path($this->path))) {
            return asset($this->path);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function scopeCover($query)
    {
        return $query->where('is_cover', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id');
    }
}