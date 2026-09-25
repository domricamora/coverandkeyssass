<?php

namespace App\Modules\Ordering\Events;

use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A signed-in guest's cart changed ($lines empty = emptied or checked out).
 * Marketing keeps a copy for abandoned-cart reminders.
 */
class CartChanged
{
    use Dispatchable;

    /** @param list<array> $lines */
    public function __construct(public readonly User $user, public readonly Restaurant $restaurant, public readonly array $lines) {}
}
