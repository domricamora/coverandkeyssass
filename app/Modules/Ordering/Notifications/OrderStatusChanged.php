<?php

namespace App\Modules\Ordering\Notifications;

use App\Modules\Ordering\Models\Order;
use Illuminate\Notifications\Notification;

/** Tells a customer their food order moved on (accepted, ready, delivered…). */
class OrderStatusChanged extends Notification
{
    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_reference' => $this->order->reference,
            'status' => $this->order->status,
            'message' => 'Order '.$this->order->reference.' from '.$this->order->restaurant?->name
                .' is now '.strtolower($this->order->statusLabel()).'.',
        ];
    }
}
