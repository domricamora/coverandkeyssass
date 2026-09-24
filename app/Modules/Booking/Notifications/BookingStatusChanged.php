<?php

namespace App\Modules\Booking\Notifications;

use App\Modules\Booking\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * Tells a marketplace customer their booking was confirmed or cancelled.
 * Stored in the database channel and shown in the customer portal.
 */
class BookingStatusChanged extends Notification
{
    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_reference' => $this->booking->reference,
            'status' => $this->booking->status,
            'message' => 'Booking '.$this->booking->reference.' at '.$this->booking->property?->name
                .' is now '.strtolower($this->booking->statusLabel()).'.',
        ];
    }
}
