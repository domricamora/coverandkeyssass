<?php

namespace App\Modules\Wallet\Providers;

use App\Modules\Booking\Events\BookingTransitioned;
use App\Modules\Booking\Models\Booking;
use App\Modules\Payments\Events\PaymentPaid;
use App\Modules\Payments\Events\PaymentRefunded;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Wallet module (Phase 08): host wallet, commissions, payouts.
 * Driven entirely by Payments and Booking events.
 */
class WalletServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        Event::listen(fn (PaymentPaid $event) => app(WalletService::class)->recordEarning($event->payment));
        Event::listen(fn (PaymentRefunded $event) => app(WalletService::class)->reverse($event->payment));
        Event::listen(function (\App\Modules\Ordering\Events\OrderTransitioned $event): void {
            if ($event->to === \App\Modules\Ordering\Models\Order::COMPLETED) {
                app(WalletService::class)->release($event->order);
            }
        });
        Event::listen(function (BookingTransitioned $event): void {
            if (in_array($event->to, [Booking::CHECKED_OUT, Booking::NO_SHOW], true)) {
                app(WalletService::class)->release($event->booking);
            }
        });
    }
}
