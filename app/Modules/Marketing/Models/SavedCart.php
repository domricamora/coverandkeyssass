<?php

namespace App\Modules\Marketing\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A signed-in guest's cart, kept so abandoned-cart reminders can find it. Cleared at checkout. */
#[Fillable(['tenant_id', 'user_id', 'restaurant_id', 'lines'])]
class SavedCart extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['lines' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class)->withoutGlobalScope('tenant');
    }
}
