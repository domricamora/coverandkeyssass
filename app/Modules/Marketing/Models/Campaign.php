<?php

namespace App\Modules\Marketing\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Booking\Models\Promotion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An email or SMS blast to a CRM audience: draft → (scheduled →) sent. */
#[Fillable(['tenant_id', 'name', 'channel', 'audience', 'subject', 'body', 'promotion_id', 'status', 'scheduled_at', 'created_by'])]
class Campaign extends Model
{
    use BelongsToTenant;

    protected $table = 'marketing_campaigns';

    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const SENT = 'sent';

    protected $attributes = ['status' => self::DRAFT];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class, 'marketing_campaign_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
