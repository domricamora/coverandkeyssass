<?php

namespace App\Modules\Messaging\Notifications;

use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Models\Thread;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Someone wrote in a conversation you are part of. */
class NewMessage extends Notification
{
    public function __construct(private readonly Thread $thread, private readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message_thread_id' => $this->thread->id,
            'message' => ($this->message->author?->name ?? 'Someone').' · '.$this->thread->subject.': '.Str::limit($this->message->body, 80),
        ];
    }
}
