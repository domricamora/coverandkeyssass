<?php

namespace App\Modules\Notify\Channels;

use App\Modules\Notify\ChannelNotification;
use App\Modules\Notify\Models\PushMessage;

/**
 * Push-ready: queues the payload in push_messages for the user's devices.
 *
 * ponytail: no FCM / APNs sender yet — a worker reading unsent rows
 * (sent_at null) and the push_devices tokens is the only missing piece.
 */
class PushChannel
{
    public function send(object $notifiable, ChannelNotification $notification): void
    {
        $push = $notification->toPush($notifiable);

        PushMessage::create(['user_id' => $notifiable->id, 'event' => $notification->event(), 'title' => $push['title'], 'body' => $push['body'], 'data' => $push['data']]);
    }
}
