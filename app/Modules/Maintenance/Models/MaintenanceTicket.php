<?php

namespace App\Modules\Maintenance\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\Room;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Modules\Marketplace\Models\Media;
use Illuminate\Support\Str;

/** A repair job for a property or one of its rooms. */
#[Fillable([
    'tenant_id', 'property_id', 'room_id', 'reference', 'title', 'description', 'category',
    'priority', 'status', 'room_out_of_order', 'cost', 'reported_by', 'assigned_to',
])]
class MaintenanceTicket extends Model
{
    use BelongsToTenant;

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const CATEGORIES = ['plumbing', 'electrical', 'hvac', 'furniture', 'appliance', 'structural', 'it', 'other'];

    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const ON_HOLD = 'on_hold';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    /** Tickets still needing work. */
    public const ACTIVE = [self::OPEN, self::IN_PROGRESS, self::ON_HOLD];

    public const TRANSITIONS = [
        self::OPEN => [self::IN_PROGRESS, self::ON_HOLD, self::RESOLVED],
        self::IN_PROGRESS => [self::ON_HOLD, self::RESOLVED],
        self::ON_HOLD => [self::IN_PROGRESS, self::RESOLVED],
        self::RESOLVED => [self::CLOSED, self::IN_PROGRESS], // close, or reopen
    ];

    protected $attributes = ['status' => self::OPEN, 'priority' => 'normal', 'room_out_of_order' => false, 'category' => 'other'];

    protected function casts(): array
    {
        return [
            'room_out_of_order' => 'boolean',
            'cost' => 'decimal:2',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public static function newReference(): string
    {
        do {
            $reference = 'MT'.Str::upper(Str::random(8));
        } while (static::query()->withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MaintenanceTicketNote::class)->oldest('id');
    }

    /** Private attachments (photos, invoices) on the polymorphic media table. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('id');
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }
}
