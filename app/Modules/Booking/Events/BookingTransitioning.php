<?php

namespace App\Modules\Booking\Events;

use App\Modules\Booking\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by BookingService::transition() after the guards pass and before
 * the status changes. A listener may veto the transition by throwing
 * (e.g. Payments refuses `refunded` when the PayMongo refund fails).
 */
class BookingTransitioning
{
    use Dispatchable;

    public function __construct(public readonly Booking $booking, public readonly string $to) {}
}
