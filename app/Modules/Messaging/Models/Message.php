<?php

namespace App\Modules\Messaging\Models;

use App\Models\User;
use App\Modules\Marketplace\Models\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['message_thread_id', 'user_id', 'side', 'body'])]
class Message extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'message_thread_id');
    }

    /** Private attachments (local disk), downloaded through an access-checked route. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('id');
    }
}
