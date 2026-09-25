<?php

namespace App\Modules\Maintenance\Notifications;

use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Notify\ChannelNotification;

/** Tells a technician a maintenance ticket is theirs. */
class TicketAssigned extends ChannelNotification
{
    public function __construct(private readonly MaintenanceTicket $ticket) {}

    public function event(): string
    {
        return 'ticket_assigned';
    }

    public function message(): string
    {
        return 'Maintenance '.$this->ticket->reference.' ('.$this->ticket->priority.'): '.$this->ticket->title
            .($this->ticket->room ? ' · room '.$this->ticket->room->room_number : '').'.';
    }

    public function link(): ?string
    {
        return route('maintenance.show', $this->ticket->reference);
    }

    public function data(): array
    {
        return ['maintenance_ticket' => $this->ticket->reference];
    }
}
