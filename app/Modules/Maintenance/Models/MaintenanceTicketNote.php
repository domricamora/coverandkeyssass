<?php

namespace App\Modules\Maintenance\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A comment on a ticket; `is_system` lines record status / assignment / cost changes. */
#[Fillable(['tenant_id', 'maintenance_ticket_id', 'user_id', 'body', 'is_system'])]
class MaintenanceTicketNote extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
