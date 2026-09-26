<?php

namespace App\Modules\Loyalty\Providers;

use App\Models\Module;
use App\Modules\Booking\Events\BookingTransitioned;
use App\Modules\Booking\Models\Booking;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Ordering\Events\OrderTransitioned;
use App\Modules\Ordering\Models\Order;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots Loyalty (Phase 23). Stays and food orders earn points the moment
 * they complete (and give them back when refunded) — only for businesses
 * running the `crm` module with the programme switched on. The screens'
 * sync() catches up anything these listeners missed.
 */
class LoyaltyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        Event::listen(function (BookingTransitioned $event): void {
            $this->forTenantOf($event->booking, fn (LoyaltyService $l) => match ($event->to) {
                Booking::CHECKED_OUT => $l->earnForBooking($event->booking),
                Booking::REFUNDED => $l->reverse('booking:'.$event->booking->id, 'Refunded stay '.$event->booking->reference),
                default => null,
            });
        });

        Event::listen(function (OrderTransitioned $event): void {
            $this->forTenantOf($event->order, fn (LoyaltyService $l) => match ($event->to) {
                Order::COMPLETED => $l->earnForOrder($event->order),
                Order::REFUNDED => $l->reverse('order:'.$event->order->id, 'Refunded order '.$event->order->reference),
                default => null,
            });
        });
    }

    private function forTenantOf(Model $row, \Closure $callback): void
    {
        $crm = Module::query()->where('slug', 'crm')->first();
        $tenant = \App\Models\Tenant::query()->find($row->tenant_id);

        if ($crm && $tenant && app(ModuleService::class)->isEnabled($crm, $tenant)) {
            app(TenantContext::class)->runAs($row, fn () => $callback(app(LoyaltyService::class)));
        }
    }
}
