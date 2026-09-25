<?php

namespace App\Modules\Reviews\Models;

use App\Modules\RestaurantManagement\Models\MenuItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A dish rated inside an order review ("Food" reviews, Phase 24). Owned by its review. */
#[Fillable(['review_id', 'menu_item_id', 'rating'])]
class ReviewItemRating extends Model
{
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withoutGlobalScope('tenant');
    }
}
