<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\PropertyManagement\Models\Room;
use App\Modules\PropertyManagement\Models\RoomType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One physical room assigned to a booking, with its per-night price
 * snapshot. The occupied nights live in `room_nights` (unique per room +
 * night) and are released when the booking stops occupying inventory.
 */
#[Fillable(['tenant_id', 'booking_id', 'room_type_id', 'room_id', 'nightly_rates', 'total'])]
class BookingRoom extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'nightly_rates' => 'array',
            'total' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }
}
