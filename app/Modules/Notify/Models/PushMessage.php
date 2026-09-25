<?php

namespace App\Modules\Notify\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Push outbox row; a delivery worker sends it to the user's devices and stamps sent_at. */
#[Fillable(['user_id', 'event', 'title', 'body', 'data', 'sent_at'])]
class PushMessage extends Model
{
    protected function casts(): array
    {
        return ['data' => 'array', 'sent_at' => 'datetime'];
    }
}
