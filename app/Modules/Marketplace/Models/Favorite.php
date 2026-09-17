<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Marketplace\Observers\FavoriteObserver;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A guest's saved listing. Favorites are personal: the controller always
 * scopes reads and writes to the authenticated user.
 */
#[Fillable(['user_id', 'favoritable_type', 'favoritable_id'])]
class Favorite extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::observe(FavoriteObserver::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }
}