<?php

use App\Modules\Customer\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer portal routes (Phase 06)
|--------------------------------------------------------------------------
|
| Personal data: every route requires authentication and only ever reads
| the signed-in user's own rows. Bookings are looked up by reference through
| Booking::forCustomer(), so another guest's reference is a plain 404.
|
*/

Route::middleware('auth')->prefix('account')->name('account.')->group(function (): void {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/bookings', [AccountController::class, 'bookings'])->name('bookings.index');
    Route::get('/bookings/{booking}', [AccountController::class, 'booking'])->name('bookings.show');
    Route::get('/bookings/{booking}/invoice', [AccountController::class, 'invoice'])->name('bookings.invoice');
    Route::post('/bookings/{booking}/cancel', [AccountController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/bookings/{booking}/review', [AccountController::class, 'review'])->name('bookings.review');
    Route::get('/reviews', [AccountController::class, 'reviews'])->name('reviews');
    Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read', [AccountController::class, 'markNotificationsRead'])->name('notifications.read');
});
