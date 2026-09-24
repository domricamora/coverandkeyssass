<?php

use App\Modules\Wallet\Controllers\AdminCommissionController;
use App\Modules\Wallet\Controllers\AdminPayoutController;
use App\Modules\Wallet\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet routes (Phase 08)
|--------------------------------------------------------------------------
| Host: active business + wallet.view / payouts.request (checked in the
| controller). Admin: Super Admin area only.
*/

Route::middleware(['auth', 'tenant.context'])->prefix('dashboard/wallet')->name('wallet.')->group(function (): void {
    Route::get('/', [WalletController::class, 'index'])->name('index');
    Route::post('/payouts', [WalletController::class, 'requestPayout'])->middleware('throttle:10,1')->name('payouts.store');
});

Route::middleware(['auth', 'super.admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/commissions', [AdminCommissionController::class, 'index'])->name('commissions.index');
    Route::post('/commissions', [AdminCommissionController::class, 'store'])->name('commissions.store');
    Route::delete('/commissions/{rate}', [AdminCommissionController::class, 'destroy'])->name('commissions.destroy');

    Route::get('/payouts', [AdminPayoutController::class, 'index'])->name('payouts.index');
    Route::patch('/payouts/{payout}', [AdminPayoutController::class, 'update'])->name('payouts.update');
});
