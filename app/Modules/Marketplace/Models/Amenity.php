<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Marketplace\Models\Concerns\HasPublicIdentity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Reference data: amenities used by property filters and detail pages. */
#[Fillable(['name', 'slug', 'category', 'icon', 'sort_order', 'is_active'])]
class Amenity extends Model
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

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_amenity');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Amenities are identified by slug — the table has no uuid column. */
    public function publicIdentityHasUuid(): bool
    {
        return false;
    }
}