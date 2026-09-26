<?php

use App\Modules\Booking\Controllers\BookingController;
use App\Modules\Booking\Controllers\PromotionController;
use App\Modules\Booking\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Booking Engine routes (Phase 05)
|--------------------------------------------------------------------------
|
| Host desk: auth + tenant context + active `booking` module; permissions
| (bookings.*, promotions.manage) are checked per action. {booking} is the
| reference and is resolved manually through the tenant scope (see
| PropertyManagementController for why implicit binding is not used).
|
*/

Route::middleware(['auth', 'tenant.context', 'module.active:booking'])
    ->prefix('dashboard/bookings')
    ->name('bookings.')
    ->group(function (): void {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/create', [BookingController::class, 'create'])->name('create');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::get('/calendar', [BookingController::class, 'calendar'])->name('calendar');

        Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
        Route::patch('/promotions/{promotion}', [PromotionController::class, 'toggle'])->name('promotions.toggle');

        Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
        Route::post('/{booking}/status', [BookingController::class, 'transition'])->name('transition');
    });

// Stay booking step 2 (React review page); signed-out guests arrive via /continue.
Route::get('/stay/{property}/review', \App\Modules\Booking\Controllers\StayReviewController::class)
    ->middleware('auth')
    ->name('stay.review');

// Marketplace reservation request — any signed-in guest.
Route::post('/property/{property}/reserve', [ReservationController::class, 'store'])
    ->middleware(['auth', 'throttle:20,1'])
    ->name('marketplace.properties.reserve');

// Front desk (React/Inertia, 2026-09-26): today's board, tape chart, room moves.
Route::middleware(['auth', 'tenant.context', 'module.active:booking'])
    ->prefix('dashboard/front-desk')
    ->name('frontdesk.')
    ->group(function (): void {
        Route::get('/', [\App\Modules\Booking\Controllers\FrontDeskController::class, 'index'])->name('index');
        Route::post('/move', [\App\Modules\Booking\Controllers\FrontDeskController::class, 'move'])->name('move');
    });
