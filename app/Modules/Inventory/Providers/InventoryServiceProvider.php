<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Ordering\Events\OrderTransitioned;
use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Inventory module (Phase 18). Food orders use stock when the
 * kitchen accepts them; cancelling an accepted order puts it back.
 */
class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'inventory');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        Event::listen(function (OrderTransitioned $event): void {
            match (true) {
                $event->to === Order::ACCEPTED => app(InventoryService::class)->consumeOrder($event->order),
                $event->to === Order::CANCELLED && $event->from === Order::ACCEPTED => app(InventoryService::class)->restoreOrder($event->order),
                default => null,
            };
        });
    }
}
