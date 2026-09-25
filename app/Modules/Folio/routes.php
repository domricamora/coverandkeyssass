<?php

use App\Modules\Folio\Controllers\FolioController;
use Illuminate\Support\Facades\Route;

/*
| Guest folio (Phase 14): front desk under the booking desk (module
| booking, permissions folio.*), and the guest's read-only copy.
*/

Route::middleware(['auth', 'tenant.context', 'module.active:booking'])
    ->prefix('dashboard/bookings/{booking}/folio')
    ->name('folio.')
    ->group(function (): void {
        Route::get('/', [FolioController::class, 'show'])->name('show');
        Route::get('/print', [FolioController::class, 'print'])->name('print');
        Route::post('/charges', [FolioController::class, 'storeCharge'])->name('charges.store');
        Route::post('/payments', [FolioController::class, 'storePayment'])->name('payments.store');
        Route::post('/refunds', [FolioController::class, 'storeRefund'])->name('refunds.store');
        Route::post('/entries/{entry}/void', [FolioController::class, 'void'])->name('void');
    });

Route::middleware('auth')->get('/account/bookings/{booking}/folio', [FolioController::class, 'guest'])->name('account.folio');
