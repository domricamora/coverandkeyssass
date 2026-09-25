<?php

namespace App\Modules\Housekeeping\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Housekeeping\Notifications\TaskAssigned;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Maintenance\Services\MaintenanceService;
use App\Modules\PropertyManagement\Models\Room;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Housekeeping (Phase 15): room cleanliness status and the task queue.
 *
 *   check-out → room dirty + checkout_clean task
 *   start     → room cleaning        complete → room clean
 *   inspect   → pass: room inspected / fail: room dirty + high-priority re-clean
 *   report    → maintenance ticket; room maintenance or out_of_order
 *              (out_of_order rooms drop out of sellable inventory)
 *
 * Runs inside the tenant (host requests, or the booking's tenant for the
 * check-out listener).
 */
class HousekeepingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function setRoomStatus(Room $room, string $status, ?User $by = null, ?string $reason = null): Room
    {
        if (! in_array($status, Room::HK_STATUSES, true)) {
            $this->fail('housekeeping_status', 'Unknown room status.');
        }

        $was = $room->housekeeping_status;
        $room->forceFill(['housekeeping_status' => $status, 'housekeeping_updated_at' => now()])->save();

        if ($was !== $status) {
            $this->audit->log('room.housekeeping', $room, ['housekeeping_status' => $was], ['housekeeping_status' => $status, 'reason' => $reason]);
        }

        return $room;
    }

    /** Listener for a booking checking out: every room becomes dirty with a cleaning job for today. */
    public function onCheckout(Booking $booking): void
    {
        foreach ($booking->rooms()->with('room')->get() as $bookingRoom) {
            $room = $bookingRoom->room;

            if (! $room) {
                continue;
            }

            $this->setRoomStatus($room, Room::HK_DIRTY, reason: 'Check-out '.$booking->reference);

            $exists = HousekeepingTask::query()->where('room_id', $room->id)->where('type', 'checkout_clean')
                ->open()->whereDate('due_on', today())->exists();

            if (! $exists) {
                HousekeepingTask::create([
                    'property_id' => $room->property_id,
                    'room_id' => $room->id,
                    'booking_id' => $booking->id,
                    'type' => 'checkout_clean',
                    'due_on' => today(),
                    'notes' => 'Guest checked out ('.$booking->reference.').',
                ]);
            }
        }
    }

    public function createTask(Room $room, string $type, string $dueOn, User $by, ?User $assignee = null, string $priority = 'normal', ?string $notes = null): HousekeepingTask
    {
        if (! in_array($type, HousekeepingTask::TYPES, true)) {
            $this->fail('type', 'Pick a task type.');
        }

        $task = HousekeepingTask::create([
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'type' => $type,
            'due_on' => $dueOn,
            'priority' => $priority === 'high' ? 'high' : 'normal',
            'notes' => $notes,
            'created_by' => $by->id,
        ]);

        if ($assignee) {
            $this->assign($task, $assignee, $by);
        }

        return $task;
    }

    public function assign(HousekeepingTask $task, ?User $assignee, User $by): HousekeepingTask
    {
        if (! in_array($task->status, [HousekeepingTask::PENDING, HousekeepingTask::IN_PROGRESS], true)) {
            $this->fail('assigned_to', 'Only open tasks can be reassigned.');
        }

        if ($assignee && ! $this->isMember($assignee)) {
            $this->fail('assigned_to', 'Assign tasks to members of this business.');
        }

        $task->forceFill(['assigned_to' => $assignee?->id])->save();

        if ($assignee && $assignee->isNot($by)) {
            $assignee->notify(new TaskAssigned($task));
        }

        return $task;
    }

    public function start(HousekeepingTask $task, User $by): HousekeepingTask
    {
        $this->mustBe($task, HousekeepingTask::PENDING, 'started');
        $this->mustOwn($task, $by);

        DB::transaction(function () use ($task, $by): void {
            $task->forceFill(['status' => HousekeepingTask::IN_PROGRESS, 'started_at' => now(), 'assigned_to' => $task->assigned_to ?? $by->id])->save();
            $this->setRoomStatus($task->room, Room::HK_CLEANING, $by);
        });

        return $task;
    }

    public function complete(HousekeepingTask $task, User $by): HousekeepingTask
    {
        $this->mustBe($task, HousekeepingTask::IN_PROGRESS, 'completed');
        $this->mustOwn($task, $by);

        DB::transaction(function () use ($task, $by): void {
            $task->forceFill(['status' => HousekeepingTask::COMPLETED, 'completed_at' => now()])->save();
            $this->setRoomStatus($task->room, Room::HK_CLEAN, $by);
        });

        return $task;
    }

    /** Supervisor check of a completed clean. A failure sends the room back with a high-priority re-clean. */
    public function inspect(HousekeepingTask $task, User $by, bool $passed, ?string $notes = null): ?HousekeepingTask
    {
        if ($task->status !== HousekeepingTask::COMPLETED || $task->inspected_at !== null) {
            $this->fail('task', 'Only completed, uninspected tasks can be inspected.');
        }

        return DB::transaction(function () use ($task, $by, $passed, $notes) {
            $task->forceFill(['inspected_by' => $by->id, 'inspected_at' => now(), 'inspection_passed' => $passed, 'inspection_notes' => $notes])->save();

            $this->audit->log('housekeeping.inspected', $task, null, ['passed' => $passed, 'notes' => $notes]);

            if ($passed) {
                $this->setRoomStatus($task->room, Room::HK_INSPECTED, $by);

                return null;
            }

            $this->setRoomStatus($task->room, Room::HK_DIRTY, $by, 'Failed inspection');

            $redo = HousekeepingTask::create([
                'property_id' => $task->property_id,
                'room_id' => $task->room_id,
                'booking_id' => $task->booking_id,
                'type' => $task->type,
                'priority' => 'high',
                'due_on' => today(),
                'notes' => 'Re-clean after failed inspection'.($notes ? ': '.$notes : '.'),
                'created_by' => $by->id,
            ]);

            return $task->assignee ? $this->assign($redo, $task->assignee, $by) : $redo;
        });
    }

    public function cancel(HousekeepingTask $task, User $by): void
    {
        if (! in_array($task->status, [HousekeepingTask::PENDING, HousekeepingTask::IN_PROGRESS], true)) {
            $this->fail('task', 'Only open tasks can be cancelled.');
        }

        $task->forceFill(['status' => HousekeepingTask::CANCELLED])->save();
        $this->audit->log('housekeeping.cancelled', $task, null, ['by' => $by->id]);
    }

    /** A room problem → maintenance ticket; the room goes to maintenance, or out of order (unsellable). */
    public function reportIssue(Room $room, string $title, ?string $description, string $priority, bool $outOfOrder, User $by): MaintenanceTicket
    {
        return DB::transaction(function () use ($room, $title, $description, $priority, $outOfOrder, $by) {
            // Resolved lazily: MaintenanceService depends on this service (room hand-back).
            $ticket = app(MaintenanceService::class)->open($room->property, $room, [
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'room_out_of_order' => $outOfOrder,
            ], $by);

            $this->setRoomStatus($room, $outOfOrder ? Room::HK_OUT_OF_ORDER : Room::HK_MAINTENANCE, $by, 'Ticket '.$ticket->reference);

            return $ticket;
        });
    }

    // ------------------------------------------------------------------

    private function isMember(User $user): bool
    {
        return (bool) app(TenantContext::class)->tenant()?->users()
            ->whereKey($user->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    private function mustBe(HousekeepingTask $task, string $status, string $verb): void
    {
        if ($task->status !== $status) {
            $this->fail('task', 'A '.str_replace('_', ' ', $task->status).' task cannot be '.$verb.'.');
        }
    }

    /** Housekeepers work their own (or unassigned) tasks; managers any. */
    private function mustOwn(HousekeepingTask $task, User $by): void
    {
        if ($task->assigned_to !== null && (int) $task->assigned_to !== (int) $by->id && ! $by->hasPermissionTo('housekeeping.manage')) {
            $this->fail('task', 'This task is assigned to someone else.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
