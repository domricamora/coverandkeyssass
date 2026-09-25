<?php

use App\Modules\Ordering\Controllers\CartController;
use App\Modules\Ordering\Controllers\CustomerOrderController;
use App\Modules\Ordering\Controllers\HostOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Online food ordering routes (Phase 11)
|--------------------------------------------------------------------------
| Public cart (session) → checkout (auth) → customer Orders tab; host order
| queue under the restaurant area (module.active:restaurant, orders.*).
*/

Route::post('/restaurant/{restaurant}/cart', [CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::patch('/cart/{key}', [CartController::class, 'update'])->name('cart.update');

Route::middleware('auth')->group(function (): void {
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->middleware('throttle:10,1')->name('cart.checkout');

    Route::prefix('account/orders')->name('account.orders.')->group(function (): void {
        Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [CustomerOrderController::class, 'show'])->name('show');
        Route::post('/{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('cancel');
        Route::post('/{order}/review', [CustomerOrderController::class, 'review'])->name('review');
        Route::post('/{order}/pay', [CustomerOrderController::class, 'pay'])->middleware('throttle:10,1')->name('pay');
        Route::get('/{order}/payment-return', [CustomerOrderController::class, 'paymentReturn'])->name('payment-return');
    });
});

Route::middleware(['auth', 'tenant.context', 'module.active:restaurant'])
    ->prefix('dashboard/restaurants/{restaurant}/orders')
    ->name('restaurants.orders.')
    ->group(function (): void {
        Route::get('/', [HostOrderController::class, 'index'])->name('index');
        Route::post('/promotions', [HostOrderController::class, 'storePromotion'])->name('promotions.store');
        Route::post('/promotions/{promotion}/toggle', [HostOrderController::class, 'togglePromotion'])->name('promotions.toggle');
        Route::get('/{order}', [HostOrderController::class, 'show'])->name('show');
        Route::post('/{order}/status', [HostOrderController::class, 'transition'])->name('transition');
        Route::post('/{order}/driver', [HostOrderController::class, 'assignDriver'])->name('driver');
    });
