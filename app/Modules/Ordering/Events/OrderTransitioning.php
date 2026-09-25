<?php

namespace App\Modules\Ordering\Events;

use App\Modules\Ordering\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired before an order changes state; a listener that throws vetoes it. */
class OrderTransitioning
{
    use Dispatchable;

    public function __construct(public readonly Order $order, public readonly string $to) {}
}
