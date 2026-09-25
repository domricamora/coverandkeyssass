<?php

namespace App\Modules\Crm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Communication history: a call, email, SMS or visit, logged by staff or by the system. */
#[Fillable(['tenant_id', 'crm_contact_id', 'user_id', 'channel', 'direction', 'subject', 'body', 'source_key', 'occurred_at'])]
class Interaction extends Model
{
    use BelongsToTenant;

    public const CHANNELS = ['email', 'sms', 'phone', 'in_person', 'chat'];

    protected $table = 'crm_interactions';

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
