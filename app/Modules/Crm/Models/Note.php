<?php

namespace App\Modules\Crm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'crm_contact_id', 'user_id', 'body'])]
class Note extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_notes';

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
