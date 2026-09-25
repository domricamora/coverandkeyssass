<?php

namespace App\Modules\Messaging\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A conversation. Not tenant-scoped on purpose: guests and support span
 * businesses, so every read goes through MessagingService::canAccess().
 */
#[Fillable(['tenant_id', 'kind', 'subject', 'guest_user_id', 'about_type', 'about_id', 'status', 'last_message_at', 'created_by'])]
class Thread extends Model
{
    protected $table = 'message_threads';

    public const GUEST_HOST = 'guest_host';

    public const GUEST_RESTAURANT = 'guest_restaurant';

    public const SUPPORT = 'support';

    public const STAFF = 'staff';

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function about(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'message_thread_id')->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'message_thread_id');
    }

    public function isGuestThread(): bool
    {
        return in_array($this->kind, [self::GUEST_HOST, self::GUEST_RESTAURANT], true);
    }

    /** Messages newer than the user's last read, written by someone else. */
    public function unreadFor(User $user): int
    {
        $readAt = $this->participants->firstWhere('user_id', $user->id)?->last_read_at;

        return $this->messages()->where('user_id', '!=', $user->id)->when($readAt, fn ($q) => $q->where('created_at', '>', $readAt))->count();
    }
}
