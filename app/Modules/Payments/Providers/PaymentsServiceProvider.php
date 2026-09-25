<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Booking\Events\BookingTransitioning;
use App\Modules\Booking\Models\Booking;
use App\Modules\Payments\Controllers\WebhookController;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Payments module (Phase 07, PayMongo). Booking and Customer do
 * not depend on it: it listens to BookingTransitioning (refunds) and adds
 * its payment panel to their booking pages through view composers.
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'payments');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        // Server-to-server: no session, no CSRF — the signature is the auth.
        Route::post('/webhooks/paymongo', WebhookController::class)
            ->middleware('throttle:120,1')
            ->name('payments.webhook');

        Event::listen(function (BookingTransitioning $event): void {
            if ($event->to === Booking::REFUNDED) {
                app(PaymentService::class)->refundBooking($event->booking);
            }
        });

        Event::listen(function (\App\Modules\Ordering\Events\OrderTransitioning $event): void {
            if ($event->to === \App\Modules\Ordering\Models\Order::REFUNDED) {
                app(PaymentService::class)->refundOrder($event->order);
            }
        });

        View::composer(['booking::bookings.show', 'customer::bookings.show'], function ($view): void {
            $booking = $view->getData()['booking'] ?? null;

            if ($booking) {
                $view->with('payments', Payment::query()->withoutGlobalScope('tenant')
                    ->where('tenant_id', $booking->tenant_id)
                    ->where('booking_id', $booking->id)
                    ->latest('id')
                    ->get());
            }
        });
    }
}
