<?php

namespace App\Modules\Notify\Support;

/**
 * Every notifiable event, who receives it and its default channels
 * (in-app is always on; the user can switch email / SMS / push per event).
 */
final class Events
{
    /** event => [label, audience guest|staff, default channels] */
    public const ALL = [
        'booking_confirmed' => ['Booking confirmed', 'guest', ['mail', 'push']],
        'booking_cancelled' => ['Booking cancelled', 'guest', ['mail', 'push']],
        'payment_received' => ['Payment received', 'guest', ['mail']],
        'payment_failed' => ['Payment failed', 'guest', ['mail', 'push']],
        'order_update' => ['Order accepted / cancelled', 'guest', ['push']],
        'order_ready' => ['Order ready', 'guest', ['mail', 'push']],
        'delivery_update' => ['Delivery update', 'guest', ['push']],
        'reservation_update' => ['Table reservation update', 'guest', ['mail', 'push']],
        'review_replied' => ['Reply to your review', 'guest', []],
        'new_message' => ['New message', 'guest', ['mail', 'push']],
        'order_received' => ['New order received', 'staff', ['push']],
        'task_assigned' => ['Housekeeping task assigned', 'staff', ['push']],
        'ticket_assigned' => ['Maintenance ticket assigned', 'staff', ['push']],
        'low_stock' => ['Low stock', 'staff', ['mail']],
        'trial_expiring' => ['Module trial / subscription expiring', 'staff', ['mail']],
    ];

    public const CHANNELS = ['mail' => 'Email', 'sms' => 'SMS', 'push' => 'Push'];

    public static function defaultOn(string $event, string $channel): bool
    {
        return in_array($channel, self::ALL[$event][2] ?? [], true);
    }
}
