<?php

namespace App\Modules\Notify\Notifications;

use App\Modules\Notify\ChannelNotification;
use App\Modules\Ordering\Models\Order;

/** Kitchen / front-of-house: a new online order came in. */
class OrderReceived extends ChannelNotification
{
    public function __construct(private readonly Order $order) {}

    public function event(): string
    {
        return 'order_received';
    }

    public function message(): string
    {
        return 'New '.str_replace('_', ' ', $this->order->fulfillment).' order '.$this->order->reference.' · '.$this->order->money($this->order->total)
            .($this->order->scheduled_for ? ' · for '.$this->order->scheduled_for->format('M j g:i A') : '').'.';
    }

    public function link(): ?string
    {
        return route('restaurants.orders.show', [$this->order->restaurant_id, $this->order->reference]);
    }

    public function data(): array
    {
        return ['order_reference' => $this->order->reference];
    }
}
