<?php

namespace App\Modules\Notify\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A phone / browser that can receive push notifications (registered by the future mobile app). */
#[Fillable(['user_id', 'platform', 'token', 'last_seen_at'])]
class PushDevice extends Model
{
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
