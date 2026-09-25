<?php

namespace App\Modules\Booking\Notifications;

use App\Modules\Booking\Models\Booking;
use App\Modules\Notify\ChannelNotification;

/**
 * Tells a marketplace customer their booking was confirmed or cancelled
 * (in-app, plus email / push per their preferences — Phase 26).
 */
class BookingStatusChanged extends ChannelNotification
{
    public function __construct(private readonly Booking $booking) {}

    public function event(): string
    {
        return $this->booking->status === Booking::CANCELLED ? 'booking_cancelled' : 'booking_confirmed';
    }

    public function message(): string
    {
        return 'Booking '.$this->booking->reference.' at '.$this->booking->property?->name
            .' is now '.strtolower($this->booking->statusLabel()).'.';
    }

    public function link(): ?string
    {
        return route('account.bookings.show', $this->booking->reference);
    }

    public function data(): array
    {
        return ['booking_reference' => $this->booking->reference, 'status' => $this->booking->status];
    }
}
