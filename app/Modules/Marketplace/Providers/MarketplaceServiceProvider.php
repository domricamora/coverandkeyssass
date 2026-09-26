<?php

namespace App\Modules\Marketplace\Providers;

use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Policies\PropertyPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Marketplace module (master plan §11: each domain is a
 * self-contained module owning its migrations, views, routes and policies).
 *
 * Views are namespaced `marketplace::`; routes are declared in routes.php
 * and loaded inside the `web` middleware group so sessions/CSRF apply.
 */
class MarketplaceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Stable, short morph aliases for the module's polymorphic rows
        // (media, reviews, favorites) so stored types read "property" /
        // "restaurant" instead of a fully-qualified class name.
        //
        // This is deliberately an additive morph map, not
        // Relation::enforceMorphMap(): enforcing a map is global, so every
        // other model participating in a polymorphic relation (User, Tenant,
        // Module for the audit trail, notifications, ...) would start throwing
        // ClassMorphViolationException. The module keeps its own guarantees
        // where they belong — FavoriteController validates the incoming
        // favoritable type against a whitelist.
        Relation::morphMap([
            'property' => Property::class,
            'restaurant' => Restaurant::class,
            // Photo owners besides listings (media.mediable_type).
            'room_type' => \App\Modules\PropertyManagement\Models\RoomType::class,
            'menu_item' => \App\Modules\RestaurantManagement\Models\MenuItem::class,
            'menu_category' => \App\Modules\RestaurantManagement\Models\MenuCategory::class,
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'marketplace');

        Gate::policy(Property::class, PropertyPolicy::class);

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        // Price-drop alerts on wish-listed stays (daily, routes/console.php): at least 5% below the
        // price when saved / last alerted; the saved price then moves down so one drop alerts once.
        \Illuminate\Support\Facades\Artisan::command('favorites:price-drops', function (): void {
            $sent = 0;
            \App\Modules\Marketplace\Models\Favorite::query()
                ->where('favoritable_type', 'property')->whereNotNull('saved_price')
                ->with(['user', 'favoritable' => fn ($q) => $q->withoutGlobalScope('tenant')])
                ->chunkById(200, function ($favorites) use (&$sent): void {
                    foreach ($favorites as $favorite) {
                        $property = $favorite->favoritable;
                        $was = (float) $favorite->saved_price;
                        $now = (float) $property?->base_price;

                        if (! $property || ! $favorite->user || $property->status !== 'published' || $now <= 0 || $now > $was * 0.95) {
                            continue;
                        }

                        $favorite->user->notify(new \App\Modules\Marketplace\Notifications\PriceDropped($property, $was));
                        $favorite->forceFill(['saved_price' => $now])->save();
                        $sent++;
                    }
                });
            $this->info("Price-drop alerts sent: {$sent}");
        })->purpose('Alert guests when a stay on their wish list gets at least 5% cheaper');
    }
}
