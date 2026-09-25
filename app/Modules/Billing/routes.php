<?php

use App\Modules\Billing\Controllers\AdminBillingController;
use App\Modules\Billing\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

/* SaaS billing (Phase 27): the business's Billing page and the Super Admin billing screen. */

Route::middleware(['auth', 'tenant.context'])->prefix('dashboard/billing')->name('billing.')->group(function (): void {
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::post('/subscribe', [BillingController::class, 'subscribe'])->name('subscribe');
    Route::post('/modules', [BillingController::class, 'addModule'])->name('modules.add');
    Route::delete('/modules/{module}', [BillingController::class, 'removeModule'])->name('modules.remove');
    Route::post('/interval', [BillingController::class, 'interval'])->name('interval');
    Route::post('/coupon', [BillingController::class, 'coupon'])->middleware('throttle:10,1')->name('coupon');
    Route::post('/cancel', [BillingController::class, 'cancel'])->name('cancel');
    Route::get('/invoices/{number}', [BillingController::class, 'invoice'])->name('invoices.show');
    Route::post('/invoices/{number}/pay', [BillingController::class, 'pay'])->middleware('throttle:10,1')->name('invoices.pay');
    Route::get('/invoices/{number}/return', [BillingController::class, 'paymentReturn'])->name('invoices.return');
});

Route::middleware(['auth', 'super.admin'])->prefix('admin/billing')->name('admin.billing.')->group(function (): void {
    Route::get('/', [AdminBillingController::class, 'index'])->name('index');
    Route::post('/invoices/{invoice}/paid', [AdminBillingController::class, 'markPaid'])->name('invoices.paid');
    Route::post('/invoices/{invoice}/void', [AdminBillingController::class, 'void'])->name('invoices.void');
    Route::post('/coupons', [AdminBillingController::class, 'storeCoupon'])->name('coupons.store');
    Route::post('/coupons/{coupon}/toggle', [AdminBillingController::class, 'toggleCoupon'])->name('coupons.toggle');
});
