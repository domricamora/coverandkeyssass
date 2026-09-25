<?php

namespace App\Modules\Maintenance\Notifications;

use App\Modules\Maintenance\Models\MaintenanceTicket;
use Illuminate\Notifications\Notification;

/** Tells a technician a maintenance ticket is theirs. */
class TicketAssigned extends Notification
{
    public function __construct(private readonly MaintenanceTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'maintenance_ticket' => $this->ticket->reference,
            'message' => 'Maintenance '.$this->ticket->reference.' ('.$this->ticket->priority.'): '.$this->ticket->title
                .($this->ticket->room ? ' · room '.$this->ticket->room->room_number : '').'.',
        ];
    }
}
