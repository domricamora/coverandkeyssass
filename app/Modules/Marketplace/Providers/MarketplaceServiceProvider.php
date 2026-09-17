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
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'marketplace');

        Gate::policy(Property::class, PropertyPolicy::class);

        Route::middleware('web')->group(__DIR__.'/../routes.php');
    }
}
