<?php

namespace App\Modules\Housekeeping\Notifications;

use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Notify\ChannelNotification;

/** Tells a housekeeper a room was assigned to them. */
class TaskAssigned extends ChannelNotification
{
    public function __construct(private readonly HousekeepingTask $task) {}

    public function event(): string
    {
        return 'task_assigned';
    }

    public function message(): string
    {
        return $this->task->typeLabel().' · room '.$this->task->room?->room_number
            .' · due '.$this->task->due_on->format('M j').($this->task->priority === 'high' ? ' (high priority)' : '').'.';
    }

    public function link(): ?string
    {
        return route('housekeeping.index', ['mine' => 1]);
    }

    public function data(): array
    {
        return ['housekeeping_task_id' => $this->task->id];
    }
}
