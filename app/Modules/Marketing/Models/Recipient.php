<?php

namespace App\Modules\Marketing\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One delivered campaign message (unique per campaign × contact, so resends skip). */
#[Fillable(['tenant_id', 'marketing_campaign_id', 'crm_contact_id', 'address', 'coupon_code', 'sent_at'])]
class Recipient extends Model
{
    use BelongsToTenant;

    protected $table = 'marketing_recipients';

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'crm_contact_id');
    }
}
