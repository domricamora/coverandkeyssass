<?php

namespace App\Modules\Notify\Channels;

use App\Modules\Marketing\Support\SmsSender;
use App\Modules\Notify\ChannelNotification;

/** Sends ChannelNotification::toSms() through the shared SMS driver. */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sms) {}

    public function send(object $notifiable, ChannelNotification $notification): void
    {
        $this->sms->send((string) $notifiable->phone, $notification->toSms($notifiable));
    }
}
