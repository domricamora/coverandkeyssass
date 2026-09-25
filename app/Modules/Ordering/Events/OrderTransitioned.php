<?php

namespace App\Modules\Ordering\Events;

use App\Modules\Ordering\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after an order changed state (Wallet releases earnings on completed). */
class OrderTransitioned
{
    use Dispatchable;

    public function __construct(public readonly Order $order, public readonly string $from, public readonly string $to) {}
}
