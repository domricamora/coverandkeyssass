<?php

namespace App\Modules\Maintenance\Services;

use App\Models\User;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Maintenance\Notifications\TicketAssigned;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\Room;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance tickets (Phase 16).
 *
 *   open → in_progress ⇄ on_hold → resolved → closed   (resolved → in_progress reopens)
 *
 * Every change leaves a system note on the ticket thread and an audit row.
 * Resolving the last active ticket of a room that housekeeping flagged
 * (maintenance / out_of_order) hands the room back as `dirty` so it is
 * cleaned and inspected before it is sold again.
 */
class MaintenanceService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HousekeepingService $housekeeping,
    ) {}

    /** @param array{title: string, description?: ?string, category?: ?string, priority?: ?string, room_out_of_order?: bool} $data */
    public function open(Property $property, ?Room $room, array $data, User $by): MaintenanceTicket
    {
        if ($room && (int) $room->property_id !== (int) $property->id) {
            $this->fail('room_id', 'That room is not part of this property.');
        }

        $ticket = MaintenanceTicket::create([
            'property_id' => $property->id,
            'room_id' => $room?->id,
            'reference' => MaintenanceTicket::newReference(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => in_array($data['category'] ?? null, MaintenanceTicket::CATEGORIES, true) ? $data['category'] : 'other',
            'priority' => in_array($data['priority'] ?? null, MaintenanceTicket::PRIORITIES, true) ? $data['priority'] : 'normal',
            'room_out_of_order' => (bool) ($data['room_out_of_order'] ?? false),
            'reported_by' => $by->id,
        ]);

        $this->note($ticket, 'Ticket opened ('.$ticket->priority.' priority, '.$ticket->category.').', $by, true);
        $this->audit->log('maintenance.reported', $ticket, null, ['room' => $room?->room_number, 'out_of_order' => $ticket->room_out_of_order]);

        return $ticket;
    }

    public function assign(MaintenanceTicket $ticket, ?User $assignee, User $by): MaintenanceTicket
    {
        if (! in_array($ticket->status, MaintenanceTicket::ACTIVE, true)) {
            $this->fail('assigned_to', 'Only active tickets can be reassigned.');
        }

        if ($assignee && ! $this->isMember($assignee)) {
            $this->fail('assigned_to', 'Assign tickets to members of this business.');
        }

        $ticket->forceFill(['assigned_to' => $assignee?->id])->save();
        $this->note($ticket, $assignee ? 'Assigned to '.$assignee->name.'.' : 'Unassigned.', $by, true);

        if ($assignee && $assignee->isNot($by)) {
            $assignee->notify(new TicketAssigned($ticket));
        }

        return $ticket;
    }

    public function transition(MaintenanceTicket $ticket, string $to, User $by, ?string $note = null): MaintenanceTicket
    {
        if (! $ticket->canTransitionTo($to)) {
            $this->fail('status', 'A '.str_replace('_', ' ', $ticket->status).' ticket cannot move to '.str_replace('_', ' ', $to).'.');
        }

        $this->mustWork($ticket, $by);

        $from = $ticket->status;

        DB::transaction(function () use ($ticket, $to, $by, $note, $from): void {
            $ticket->status = $to;
            match ($to) {
                MaintenanceTicket::IN_PROGRESS => $ticket->forceFill(['started_at' => $ticket->started_at ?? now(), 'resolved_at' => null, 'assigned_to' => $ticket->assigned_to ?? $by->id]),
                MaintenanceTicket::RESOLVED => $ticket->forceFill(['resolved_at' => now()]),
                MaintenanceTicket::CLOSED => $ticket->forceFill(['closed_at' => now()]),
                default => null,
            };
            $ticket->save();

            $this->note($ticket, Str::headline($from).' → '.Str::headline($to).($note ? ': '.$note : '.'), $by, true);

            if ($to === MaintenanceTicket::RESOLVED) {
                $this->releaseRoom($ticket, $by);
            }
        });

        $this->audit->log('maintenance.'.$to, $ticket, ['status' => $from], ['status' => $to, 'note' => $note]);

        return $ticket;
    }

    public function setCost(MaintenanceTicket $ticket, float $cost, User $by): MaintenanceTicket
    {
        if ($ticket->status === MaintenanceTicket::CLOSED) {
            $this->fail('cost', 'Closed tickets are read-only.');
        }

        $was = $ticket->cost;
        $ticket->forceFill(['cost' => round($cost, 2)])->save();
        $this->note($ticket, 'Cost set to ₱'.number_format($cost, 2).'.', $by, true);
        $this->audit->log('maintenance.cost', $ticket, ['cost' => $was], ['cost' => $ticket->cost]);

        return $ticket;
    }

    public function addNote(MaintenanceTicket $ticket, string $body, User $by): void
    {
        if ($ticket->status === MaintenanceTicket::CLOSED) {
            $this->fail('body', 'Closed tickets are read-only.');
        }

        $this->note($ticket, $body, $by);
    }

    /** Stored privately (local disk); downloads go through an authorised route. */
    public function attach(MaintenanceTicket $ticket, UploadedFile $file, User $by): void
    {
        if ($ticket->status === MaintenanceTicket::CLOSED) {
            $this->fail('file', 'Closed tickets are read-only.');
        }

        $path = $file->store('maintenance/'.$ticket->tenant_id.'/'.$ticket->id, 'local');

        $ticket->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'kind' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'document',
            'alt' => Str::limit($file->getClientOriginalName(), 190, ''),
        ]);

        $this->note($ticket, 'Attached '.$file->getClientOriginalName().'.', $by, true);
    }

    // ------------------------------------------------------------------

    private function releaseRoom(MaintenanceTicket $ticket, User $by): void
    {
        $room = $ticket->room;

        if (! $room || ! in_array($room->housekeeping_status, [Room::HK_MAINTENANCE, Room::HK_OUT_OF_ORDER], true)) {
            return;
        }

        $stillOpen = MaintenanceTicket::query()->where('room_id', $room->id)->whereKeyNot($ticket->id)->active()->exists();

        if (! $stillOpen) {
            $this->housekeeping->setRoomStatus($room, Room::HK_DIRTY, $by, 'Repaired ('.$ticket->reference.')');
        }
    }

    private function note(MaintenanceTicket $ticket, string $body, ?User $by, bool $system = false): void
    {
        $ticket->notes()->create(['user_id' => $by?->id, 'body' => $body, 'is_system' => $system]);
    }

    /** Workers progress their own or unassigned tickets; managers any. */
    private function mustWork(MaintenanceTicket $ticket, User $by): void
    {
        if ($ticket->assigned_to !== null && (int) $ticket->assigned_to !== (int) $by->id && ! $by->hasPermissionTo('maintenance.manage')) {
            $this->fail('status', 'This ticket is assigned to someone else.');
        }
    }

    private function isMember(User $user): bool
    {
        return (bool) app(TenantContext::class)->tenant()?->users()->whereKey($user->id)->wherePivot('status', 'active')->exists();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
