<?php

namespace App\Modules\Marketing\Providers;

use App\Models\Tenant;
use App\Modules\Marketing\Models\SavedCart;
use App\Modules\Marketing\Services\AutomationService;
use App\Modules\Marketing\Services\CampaignService;
use App\Modules\Ordering\Events\CartChanged;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots Marketing (Phase 22). Keeps signed-in guests' carts for the
 * abandoned-cart follow-up; `php artisan marketing:run` sends due
 * scheduled campaigns and the enabled automations for every business.
 */
class MarketingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'marketing');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        Event::listen(function (CartChanged $event): void {
            app(TenantContext::class)->runAs($event->restaurant, function () use ($event): void {
                $event->lines === []
                    ? SavedCart::query()->where('user_id', $event->user->id)->where('restaurant_id', $event->restaurant->id)->delete()
                    : SavedCart::query()->updateOrCreate(['user_id' => $event->user->id, 'restaurant_id' => $event->restaurant->id], ['lines' => $event->lines])->touch();
            });
        });

        if ($this->app->runningInConsole()) {
            Artisan::command('marketing:run', function (): void {
                $context = app(TenantContext::class);

                foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
                    $context->set($tenant);
                    $campaigns = app(CampaignService::class)->runScheduled();
                    $sent = array_sum(app(AutomationService::class)->run());
                    $this->info("{$tenant->name}: {$campaigns} campaign(s), {$sent} automated message(s)");
                }

                $context->forget();
            })->purpose('Send due campaigns and automated follow-ups');
        }
    }
}
