<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Marketplace\Models\Concerns\HasPublicIdentity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reference data for property categories (hotel, resort, B&B, villa…).
 */
#[Fillable(['name', 'slug', 'category', 'description', 'icon', 'sort_order', 'is_active'])]
class PropertyType extends Model
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

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Property types are identified by slug — the table has no uuid column. */
    public function publicIdentityHasUuid(): bool
    {
        return false;
    }
}