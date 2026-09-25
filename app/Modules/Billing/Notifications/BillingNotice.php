<?php

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Notify\ChannelNotification;

/** To owners: an invoice was issued / paid, or payment is overdue. */
class BillingNotice extends ChannelNotification
{
    public function __construct(private readonly string $event, private readonly Invoice $invoice, private readonly string $text) {}

    public function event(): string
    {
        return $this->event;
    }

    public function message(): string
    {
        return $this->text;
    }

    public function link(): ?string
    {
        return route('billing.invoices.show', $this->invoice->number);
    }

    public function data(): array
    {
        return ['invoice_number' => $this->invoice->number];
    }
}
