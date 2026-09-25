<?php

namespace App\Modules\Folio\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One line of a guest folio: a charge, a payment or a refund. */
#[Fillable([
    'tenant_id', 'booking_id', 'type', 'category', 'description', 'quantity',
    'unit_amount', 'amount', 'service_date', 'reference', 'source_key', 'posted_by',
])]
class FolioEntry extends Model
{
    use BelongsToTenant;

    public const CHARGE = 'charge';

    public const PAYMENT = 'payment';

    public const REFUND = 'refund';

    /** Charge categories (master plan: room, food, room service, laundry, minibar, activities, transport, other). */
    public const CHARGE_CATEGORIES = ['room', 'food', 'room_service', 'laundry', 'minibar', 'activities', 'transport', 'other'];

    /** Categories staff can post by hand — room nights and room service come from their records. */
    public const MANUAL_CHARGE_CATEGORIES = ['food', 'laundry', 'minibar', 'activities', 'transport', 'other'];

    public const PAYMENT_METHODS = ['cash', 'card', 'transfer', 'ewallet'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'service_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /** Entries typed in by staff; synced ones are voided by their source instead. */
    public function isManual(): bool
    {
        return $this->source_key === null;
    }

    public function categoryLabel(): string
    {
        return Str::headline($this->category);
    }
}
