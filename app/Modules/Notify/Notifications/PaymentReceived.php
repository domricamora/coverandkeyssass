<?php

namespace App\Modules\Notify\Notifications;

use App\Modules\Notify\ChannelNotification;
use App\Modules\Payments\Models\Payment;

/** Receipt for an online payment (booking or food order). */
class PaymentReceived extends ChannelNotification
{
    public function __construct(private readonly Payment $payment, private readonly string $reference, private readonly string $link) {}

    public function event(): string
    {
        return 'payment_received';
    }

    public function message(): string
    {
        return 'We received your payment of '.$this->payment->currency.' '.number_format((float) $this->payment->amount, 2).' for '.$this->reference.'. Thank you!';
    }

    public function link(): ?string
    {
        return $this->link;
    }

    public function data(): array
    {
        return ['payment_id' => $this->payment->id, 'reference' => $this->reference];
    }
}
