<?php

namespace App\Modules\RestaurantManagement\Notifications;

use App\Modules\RestaurantManagement\Models\TableReservation;
use Illuminate\Notifications\Notification;

/** Tells a customer their table reservation was confirmed or cancelled. */
class ReservationStatusChanged extends Notification
{
    public function __construct(private readonly TableReservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reservation_reference' => $this->reservation->reference,
            'status' => $this->reservation->status,
            'message' => 'Table reservation '.$this->reservation->reference.' at '.$this->reservation->restaurant?->name
                .' ('.$this->reservation->reserved_at->format('M j, g:i A').') is now '.strtolower($this->reservation->statusLabel()).'.',
        ];
    }
}
