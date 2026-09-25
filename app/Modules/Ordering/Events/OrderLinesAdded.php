<?php

namespace App\Modules\Ordering\Events;

use App\Modules\Ordering\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Lines were added to an accepted register ticket (Inventory consumes them). */
class OrderLinesAdded
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
