<?php

namespace App\Modules\RestaurantManagement\Notifications;

use App\Modules\Notify\ChannelNotification;
use App\Modules\RestaurantManagement\Models\TableReservation;

/** Tells a customer their table reservation was confirmed or cancelled. */
class ReservationStatusChanged extends ChannelNotification
{
    public function __construct(private readonly TableReservation $reservation) {}

    public function event(): string
    {
        return 'reservation_update';
    }

    public function message(): string
    {
        return 'Table reservation '.$this->reservation->reference.' at '.$this->reservation->restaurant?->name
            .' ('.$this->reservation->reserved_at->format('M j, g:i A').') is now '.strtolower($this->reservation->statusLabel()).'.';
    }

    public function link(): ?string
    {
        return route('account.reservations.index');
    }

    public function data(): array
    {
        return ['reservation_reference' => $this->reservation->reference, 'status' => $this->reservation->status];
    }
}
