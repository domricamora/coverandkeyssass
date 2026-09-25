<?php

namespace App\Modules\PlatformAdmin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A guest's report of a property or restaurant listing (Phase 29). */
#[Fillable(['user_id', 'reportable_type', 'reportable_id', 'reason', 'details', 'status', 'resolved_by', 'resolution_note', 'resolved_at'])]
class ContentReport extends Model
{
    public const REASONS = [
        'misleading' => 'Misleading photos or description',
        'scam' => 'Scam or fraud',
        'unsafe' => 'Unsafe or illegal',
        'closed' => 'Closed / does not exist',
        'offensive' => 'Offensive content',
        'other' => 'Something else',
    ];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope('tenant');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
