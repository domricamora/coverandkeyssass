<?php

namespace App\Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone in a thread, and how far they have read. */
#[Fillable(['message_thread_id', 'user_id', 'side', 'last_read_at'])]
class Participant extends Model
{
    protected $table = 'message_participants';

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
