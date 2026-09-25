<?php

use App\Modules\Pos\Controllers\PosController;
use Illuminate\Support\Facades\Route;

/*
| Point of sale (Phase 19) — `pos` module (depends on `restaurant`);
| pos.use / discount / refund / manage.
*/

Route::middleware(['auth', 'tenant.context', 'module.active:pos'])
    ->prefix('dashboard/pos/{restaurant}')
    ->name('pos.')
    ->group(function (): void {
        Route::get('/', [PosController::class, 'register'])->name('register');
        Route::get('/kitchen', [PosController::class, 'kitchen'])->name('kitchen');
        Route::post('/kitchen/{ticket}/bump', [PosController::class, 'bump'])->name('kitchen.bump');

        Route::post('/sessions', [PosController::class, 'openSession'])->name('sessions.open');
        Route::get('/sessions/{session}', [PosController::class, 'showSession'])->name('sessions.show');
        Route::post('/sessions/{session}/close', [PosController::class, 'closeSession'])->name('sessions.close');

        Route::post('/tickets', [PosController::class, 'storeTicket'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [PosController::class, 'showTicket'])->name('tickets.show');
        Route::get('/tickets/{ticket}/receipt', [PosController::class, 'receipt'])->name('tickets.receipt');
        Route::post('/tickets/{ticket}/lines', [PosController::class, 'addLine'])->name('tickets.lines');
        Route::post('/tickets/{ticket}/discount', [PosController::class, 'discount'])->name('tickets.discount');
        Route::post('/tickets/{ticket}/pay', [PosController::class, 'pay'])->name('tickets.pay');
        Route::post('/tickets/{ticket}/room', [PosController::class, 'chargeToRoom'])->name('tickets.room');
        Route::post('/tickets/{ticket}/close', [PosController::class, 'close'])->name('tickets.close');
        Route::post('/tickets/{ticket}/cancel', [PosController::class, 'cancel'])->name('tickets.cancel');
        Route::post('/tickets/{ticket}/refund', [PosController::class, 'refund'])->name('tickets.refund');
    });
