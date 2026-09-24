<?php

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired once, when a payment is first recorded as paid. */
class PaymentPaid
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
