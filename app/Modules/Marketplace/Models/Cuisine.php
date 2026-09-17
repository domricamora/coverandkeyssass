<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Marketplace\Models\Concerns\HasPublicIdentity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Reference data: cuisines used by restaurant listings and filters. */
#[Fillable(['name', 'slug', 'icon', 'sort_order', 'is_active'])]
class Cuisine extends Model
{
    use HasFactory;
    use HasPublicIdentity;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'cuisine_restaurant');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Cuisines are identified by slug — the table has no uuid column. */
    public function publicIdentityHasUuid(): bool
    {
        return false;
    }
}