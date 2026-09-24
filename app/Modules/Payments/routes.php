<?php

use App\Modules\Payments\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payments routes (Phase 07) — guest side, inside the customer portal.
|--------------------------------------------------------------------------
| The PayMongo webhook is registered in PaymentsServiceProvider, outside
| the `web` group.
*/

Route::middleware('auth')->prefix('account')->name('account.')->group(function (): void {
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/bookings/{booking}/pay', [PaymentController::class, 'pay'])->middleware('throttle:10,1')->name('payments.pay');
    Route::get('/bookings/{booking}/payment-return', [PaymentController::class, 'return'])->name('payments.return');
});
