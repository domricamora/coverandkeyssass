<?php

namespace App\Modules\Notify\Notifications;

use App\Modules\Notify\ChannelNotification;
use App\Modules\Payments\Models\Payment;

/** An online payment was declined — the guest can try again from the link. */
class PaymentFailed extends ChannelNotification
{
    public function __construct(private readonly Payment $payment, private readonly string $reference, private readonly string $link) {}

    public function event(): string
    {
        return 'payment_failed';
    }

    public function message(): string
    {
        return 'Your payment for '.$this->reference.' did not go through'.($this->payment->failure_reason ? ' ('.$this->payment->failure_reason.')' : '').'. You can try again anytime.';
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
