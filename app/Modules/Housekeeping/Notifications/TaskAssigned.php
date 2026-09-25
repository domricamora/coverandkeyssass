<?php

namespace App\Modules\Housekeeping\Notifications;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use Illuminate\Notifications\Notification;

/** Tells a housekeeper a room was assigned to them. */
class TaskAssigned extends Notification
{
    public function __construct(private readonly HousekeepingTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'housekeeping_task_id' => $this->task->id,
            'message' => $this->task->typeLabel().' · room '.$this->task->room?->room_number
                .' · due '.$this->task->due_on->format('M j').($this->task->priority === 'high' ? ' (high priority)' : '').'.',
        ];
    }
}
