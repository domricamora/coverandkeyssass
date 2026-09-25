<?php

namespace App\Modules\Notify\Providers;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\Notify\Notifications\OrderReceived;
use App\Modules\Notify\Notifications\PaymentFailed as PaymentFailedNotification;
use App\Modules\Notify\Notifications\PaymentReceived;
use App\Modules\Notify\Notifications\TrialExpiring;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Events\PaymentFailed;
use App\Modules\Payments\Events\PaymentPaid;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Notify module (Phase 26): channel preferences, SMS / push
 * delivery, the staff notification centre and the payment / order / trial
 * notifications. `php artisan notifications:trials` warns owners daily.
 */
class NotifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'notify');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        Event::listen(fn (PaymentPaid $event) => $this->notifyPayer($event->payment, true));
        Event::listen(fn (PaymentFailed $event) => $this->notifyPayer($event->payment, false));
        Event::listen(function (OrderPlaced $event): void {
            self::staffWith($event->order->tenant_id, 'orders.view')
                ->each(fn (User $user) => $user->notify(new OrderReceived($event->order)));
        });

        if ($this->app->runningInConsole()) {
            Artisan::command('notifications:trials', function (): void {
                $sent = 0;
                TenantModule::query()->with('module')
                    ->whereBetween('trial_ends_at', [now(), now()->addDays(3)])
                    ->get()
                    ->each(function (TenantModule $subscription) use (&$sent): void {
                        $tenant = Tenant::find($subscription->tenant_id);
                        foreach (NotifyServiceProvider::staffWith($subscription->tenant_id, 'tenants.update')->filter(fn (User $user) => $user->hasRole('owner', $subscription->tenant_id)) as $owner) {
                            $already = $owner->notifications()->where('type', TrialExpiring::class)
                                ->where('data->tenant_module_id', $subscription->id)
                                ->where('created_at', '>=', today())->exists();
                            if (! $already) {
                                $owner->notify(new TrialExpiring($subscription, $tenant));
                                $sent++;
                            }
                        }
                    });
                $this->info("Sent {$sent} trial reminder(s).");
            })->purpose('Warn business owners about module trials ending within 3 days');
        }
    }

    /** Active members of a business holding a permission there. */
    public static function staffWith(int $tenantId, string $permission)
    {
        $tenant = Tenant::find($tenantId);

        return $tenant?->users()->wherePivot('status', 'active')->get()
            ->filter(fn (User $user) => $user->hasPermissionTo($permission, $tenantId))
            ->values() ?? collect();
    }

    private function notifyPayer(Payment $payment, bool $paid): void
    {
        $user = User::find($payment->user_id);
        if (! $user) {
            return;
        }

        if ($payment->booking_id) {
            $booking = $payment->booking()->withoutGlobalScope('tenant')->first();
            [$reference, $link] = [$booking?->reference, $booking ? route('account.bookings.show', $booking->reference) : null];
        } else {
            $order = $payment->order()->withoutGlobalScope('tenant')->first();
            [$reference, $link] = [$order?->reference, $order ? route('account.orders.show', $order->reference) : null];
        }

        if ($reference) {
            $user->notify($paid ? new PaymentReceived($payment, $reference, $link) : new PaymentFailedNotification($payment, $reference, $link));
        }
    }
}
