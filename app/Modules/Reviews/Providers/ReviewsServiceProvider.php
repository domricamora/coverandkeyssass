<?php

namespace App\Modules\Reviews\Providers;

use App\Modules\Reviews\Services\ReviewService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Boots Reviews (Phase 24): verified stay / order / visit reviews with
 * category, room and dish ratings, host replies, platform moderation.
 * Adds the rating summary to the public listing pages.
 */
class ReviewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'reviews');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        View::composer(['marketplace::properties.show', 'marketplace::restaurants.show'], function ($view): void {
            // The property page names its listing `property`, the restaurant page `listing`.
            if ($listing = $view->getData()['listing'] ?? $view->getData()['property'] ?? null) {
                $view->with('reviewSummary', app(ReviewService::class)->summary($listing));
            }
        });
    }
}
