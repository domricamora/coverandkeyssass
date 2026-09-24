<?php

namespace App\Modules\Booking\Events;

use App\Modules\Booking\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a booking state change was saved (e.g. Wallet releases earnings on check-out). */
class BookingTransitioned
{
    use Dispatchable;

    public function __construct(public readonly Booking $booking, public readonly string $from, public readonly string $to) {}
}
