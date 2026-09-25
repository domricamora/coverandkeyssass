<?php

namespace App\Modules\Messaging\Notifications;

use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Models\Thread;
use App\Modules\Notify\ChannelNotification;
use Illuminate\Support\Str;

/** Someone wrote in a conversation you are part of. */
class NewMessage extends ChannelNotification
{
    public function __construct(private readonly Thread $thread, private readonly Message $message) {}

    public function event(): string
    {
        return 'new_message';
    }

    public function message(): string
    {
        return ($this->message->author?->name ?? 'Someone').' · '.$this->thread->subject.': '.Str::limit($this->message->body, 80);
    }

    public function link(): ?string
    {
        return match (true) {
            $this->message->side !== 'guest' && $this->thread->guest_user_id !== null && $this->thread->kind !== Thread::STAFF => route('account.messages.show', $this->thread->id),
            $this->thread->kind === Thread::SUPPORT => route('admin.support.show', $this->thread->id),
            default => route('messages.show', $this->thread->id),
        };
    }

    public function data(): array
    {
        return ['message_thread_id' => $this->thread->id];
    }
}
