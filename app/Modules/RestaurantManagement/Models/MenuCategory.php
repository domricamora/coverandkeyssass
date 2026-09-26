<?php

namespace App\Modules\RestaurantManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A menu section ("Mains", "Drinks") of a restaurant. */
#[Fillable(['tenant_id', 'restaurant_id', 'name', 'description', 'is_active', 'sort_order'])]
class MenuCategory extends Model
{
    use BelongsToTenant;
    use \App\Modules\Marketplace\Models\Concerns\HasMedia; // section banner photo

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
