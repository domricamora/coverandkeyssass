<?php

use App\Modules\Housekeeping\Controllers\HousekeepingController;
use Illuminate\Support\Facades\Route;

/*
| Housekeeping (Phase 15) — part of the `workforce` module
| (permissions housekeeping.view / work / manage).
*/

Route::middleware(['auth', 'tenant.context', 'module.active:workforce'])
    ->prefix('dashboard/housekeeping')
    ->name('housekeeping.')
    ->group(function (): void {
        Route::get('/', [HousekeepingController::class, 'index'])->name('index');
        Route::post('/rooms/{room}/status', [HousekeepingController::class, 'setStatus'])->name('rooms.status');
        Route::post('/rooms/{room}/issues', [HousekeepingController::class, 'reportIssue'])->name('rooms.issue');
        Route::post('/tasks', [HousekeepingController::class, 'storeTask'])->name('tasks.store');
        Route::post('/tasks/{task}/assign', [HousekeepingController::class, 'assign'])->name('tasks.assign');
        Route::post('/tasks/{task}/start', [HousekeepingController::class, 'start'])->name('tasks.start');
        Route::post('/tasks/{task}/complete', [HousekeepingController::class, 'complete'])->name('tasks.complete');
        Route::post('/tasks/{task}/inspect', [HousekeepingController::class, 'inspect'])->name('tasks.inspect');
        Route::post('/tasks/{task}/cancel', [HousekeepingController::class, 'cancel'])->name('tasks.cancel');
    });
