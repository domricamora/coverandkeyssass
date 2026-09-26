<?php

use App\Modules\Marketplace\Controllers\FavoriteController;
use App\Modules\Marketplace\Controllers\HomeController;
use App\Modules\Marketplace\Controllers\PropertyController;
use App\Modules\Marketplace\Controllers\PropertySearchController;
use App\Modules\Marketplace\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketplace routes (public)
|--------------------------------------------------------------------------
|
| Loaded by MarketplaceServiceProvider inside the `web` middleware group.
| URLs follow the master plan §PHASE 03:
|
|   /hotels                  listing search + filters
|   /hotels/{location}       destination landing page (SEO)
|   /property/{slug}         property detail
|   /restaurants             restaurant search
|   /restaurant/{slug}       restaurant detail
|
| Binding: public routes resolve the listing by slug inside the controller
| through Property::publicQuery() / Restaurant::publicQuery(), so drafts,
| suspended and soft-deleted rows are a plain 404 (implicit route-model
| binding cannot be used here — the tenant scope would deny every public
| lookup).
|
*/

Route::get('/hotels', [PropertySearchController::class, 'index'])->name('marketplace.hotels');
Route::get('/search', [PropertySearchController::class, 'index'])->name('marketplace.search');
Route::get('/hotels/{location}', [PropertySearchController::class, 'location'])->name('marketplace.locations.show');
Route::get('/property/{property}', [PropertyController::class, 'show'])->name('marketplace.properties.show');
Route::get('/property/{property}/quote', \App\Modules\Marketplace\Controllers\StayQuoteController::class)->middleware('throttle:120,1')->name('marketplace.properties.quote');

Route::get('/restaurants', [RestaurantController::class, 'index'])->name('marketplace.restaurants.index');
Route::get('/restaurant/{restaurant}', [RestaurantController::class, 'show'])->name('marketplace.restaurants.show');

/*
| Wish list — personal data, so every route requires authentication and the
| controller only ever reads/writes rows owned by the current user.
*/
Route::middleware('auth')->prefix('favorites')->name('marketplace.favorites.')->group(function () {
    Route::get('/', [FavoriteController::class, 'index'])->name('index');
    Route::post('/{type}/{id}', [FavoriteController::class, 'store'])->name('store');
    Route::delete('/{type}/{id}', [FavoriteController::class, 'destroy'])->name('destroy');
});

/*
| Marketplace landing page (`/stays`) — kept separate from the marketing
| homepage at `/` so both surfaces can evolve independently.
*/
Route::get('/stays', [HomeController::class, 'index'])->name('marketplace.home');