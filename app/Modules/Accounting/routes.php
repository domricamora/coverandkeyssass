<?php

use App\Modules\Accounting\Controllers\AccountingController;
use App\Modules\Accounting\Controllers\BooksController;
use Illuminate\Support\Facades\Route;

/*
| Accounting (Phase 20) — `finance` module; accounting.view / manage.
*/

Route::middleware(['auth', 'tenant.context', 'module.active:finance'])
    ->prefix('dashboard/accounting')
    ->name('accounting.')
    ->group(function (): void {
        Route::get('/', [AccountingController::class, 'index'])->name('index');
        Route::get('/trial-balance', [AccountingController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/journal', [AccountingController::class, 'journal'])->name('journal');
        Route::get('/payables', [AccountingController::class, 'payables'])->name('payables');
        Route::post('/suppliers/{supplier}/payments', [AccountingController::class, 'paySupplier'])->name('suppliers.pay');

        Route::get('/expenses', [BooksController::class, 'expenses'])->name('expenses');
        Route::post('/expenses', [BooksController::class, 'storeExpense'])->name('expenses.store');

        Route::get('/invoices', [BooksController::class, 'invoices'])->name('invoices');
        Route::post('/invoices', [BooksController::class, 'storeInvoice'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [BooksController::class, 'showInvoice'])->name('invoices.show');
        Route::post('/invoices/{invoice}/issue', [BooksController::class, 'issueInvoice'])->name('invoices.issue');
        Route::post('/invoices/{invoice}/payments', [BooksController::class, 'payInvoice'])->name('invoices.pay');
        Route::post('/invoices/{invoice}/void', [BooksController::class, 'voidInvoice'])->name('invoices.void');
    });
