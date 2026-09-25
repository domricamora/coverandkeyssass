<?php

namespace App\Modules\Ordering\Events;

use App\Modules\Ordering\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after an online order (cart checkout) is committed. */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
