<?php

use App\Modules\Api\Controllers\AuthController;
use App\Modules\Api\Controllers\BusinessController;
use App\Modules\Api\Controllers\CatalogController;
use App\Modules\Api\Controllers\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — loaded by ApiServiceProvider under /api/v1 (name prefix `api.`)
|--------------------------------------------------------------------------
|
| Auth: `Authorization: Bearer <token>` from POST /auth/token. Tokens carry
| the `read` ability and, unless requested read-only, `write`; every mutating
| route requires `write`. Business routes also need `X-Tenant: <slug>` and the
| same permission the matching dashboard screen checks.
|
*/

Route::post('/auth/token', [AuthController::class, 'issue'])->middleware('throttle:6,1')->name('auth.token');

// Public marketplace catalogue.
Route::get('/properties', [CatalogController::class, 'properties'])->name('properties.index');
Route::get('/properties/{slug}', [CatalogController::class, 'property'])->name('properties.show');
Route::get('/properties/{slug}/rooms', [CatalogController::class, 'rooms'])->name('properties.rooms');
Route::get('/properties/{slug}/reviews', [CatalogController::class, 'propertyReviews'])->name('properties.reviews');
Route::get('/restaurants', [CatalogController::class, 'restaurants'])->name('restaurants.index');
Route::get('/restaurants/{slug}', [CatalogController::class, 'restaurant'])->name('restaurants.show');
Route::get('/restaurants/{slug}/menu', [CatalogController::class, 'menu'])->name('restaurants.menu');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::delete('/auth/token', [AuthController::class, 'revoke'])->name('auth.revoke');

    // The signed-in customer.
    Route::prefix('me')->name('me.')->group(function () {
        Route::get('/bookings', [MeController::class, 'bookings'])->name('bookings');
        Route::get('/bookings/{reference}', [MeController::class, 'booking'])->name('bookings.show');
        Route::get('/orders', [MeController::class, 'orders'])->name('orders');
        Route::get('/payments', [MeController::class, 'payments'])->name('payments');
        Route::get('/notifications', [MeController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{id}/read', [MeController::class, 'readNotification'])->middleware('ability:write')->name('notifications.read');
        Route::get('/messages', [MeController::class, 'messages'])->name('messages');
    });

    Route::middleware(['ability:write', 'throttle:20,1'])->group(function () {
        Route::post('/properties/{slug}/bookings', [MeController::class, 'reserve'])->name('bookings.store');
        Route::post('/restaurants/{slug}/orders', [MeController::class, 'order'])->name('orders.store');
    });

    // Staff of the business named in X-Tenant.
    Route::prefix('business')->name('business.')->middleware('api.tenant')->group(function () {
        Route::get('/users', [BusinessController::class, 'users'])->name('users');
        Route::get('/properties', [BusinessController::class, 'properties'])->name('properties');
        Route::get('/rooms', [BusinessController::class, 'rooms'])->name('rooms');
        Route::get('/bookings', [BusinessController::class, 'bookings'])->name('bookings');
        Route::post('/bookings/{reference}/status', [BusinessController::class, 'bookingStatus'])->middleware('ability:write')->name('bookings.status');
        Route::get('/restaurants', [BusinessController::class, 'restaurants'])->name('restaurants');
        Route::get('/orders', [BusinessController::class, 'orders'])->name('orders');
        Route::post('/orders/{reference}/status', [BusinessController::class, 'orderStatus'])->middleware('ability:write')->name('orders.status');
        Route::get('/delivery/zones', [BusinessController::class, 'deliveryZones'])->name('delivery.zones');
        Route::get('/payments', [BusinessController::class, 'payments'])->name('payments');
        Route::get('/customers', [BusinessController::class, 'customers'])->name('customers');
        Route::get('/reviews', [BusinessController::class, 'reviews'])->name('reviews');
        Route::get('/subscription', [BusinessController::class, 'subscription'])->name('subscription');
    });
});
