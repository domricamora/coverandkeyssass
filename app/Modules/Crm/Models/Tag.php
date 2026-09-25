<?php

namespace App\Modules\Crm\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'name'])]
class Tag extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_tags';

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'crm_contact_tag', 'crm_tag_id', 'crm_contact_id');
    }
}
