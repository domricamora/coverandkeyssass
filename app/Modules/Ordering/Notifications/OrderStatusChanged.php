<?php

namespace App\Modules\Ordering\Notifications;

use App\Modules\Notify\ChannelNotification;
use App\Modules\Ordering\Models\Order;

/** Tells a customer their food order moved on (accepted, ready, on the way, delivered…). */
class OrderStatusChanged extends ChannelNotification
{
    public function __construct(private readonly Order $order) {}

    public function event(): string
    {
        return match ($this->order->status) {
            Order::READY => 'order_ready',
            Order::OUT_FOR_DELIVERY, Order::DELIVERED => 'delivery_update',
            default => 'order_update',
        };
    }

    public function message(): string
    {
        return 'Order '.$this->order->reference.' from '.$this->order->restaurant?->name
            .' is now '.strtolower($this->order->statusLabel())
            .($this->order->status === Order::OUT_FOR_DELIVERY && $this->order->estimated_at ? ' — arriving around '.$this->order->estimated_at->format('g:i A') : '').'.';
    }

    public function link(): ?string
    {
        return route('account.orders.show', $this->order->reference);
    }

    public function data(): array
    {
        return ['order_reference' => $this->order->reference, 'status' => $this->order->status];
    }
}
