<?php

use App\Modules\Workforce\Controllers\MyWorkController;
use App\Modules\Workforce\Controllers\RosterController;
use App\Modules\Workforce\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

/*
| Staff management (Phase 17) — `workforce` module. Manager screens need
| staff.* / schedules.manage / attendance.manage / leave.approve; "My work"
| needs only an employee record linked to the signed-in account.
*/

Route::middleware(['auth', 'tenant.context', 'module.active:workforce'])->prefix('dashboard')->group(function (): void {
    Route::prefix('staff')->name('staff.')->group(function (): void {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::post('/departments', [StaffController::class, 'storeDepartment'])->name('departments.store');
        Route::post('/positions', [StaffController::class, 'storePosition'])->name('positions.store');

        Route::get('/schedule', [RosterController::class, 'schedule'])->name('schedule');
        Route::post('/shifts', [RosterController::class, 'storeShift'])->name('shifts.store');
        Route::post('/shifts/{shift}/cancel', [RosterController::class, 'cancelShift'])->name('shifts.cancel');
        Route::get('/attendance', [RosterController::class, 'attendance'])->name('attendance');
        Route::post('/attendance/{employee}', [RosterController::class, 'clock'])->name('attendance.clock');
        Route::get('/leave', [RosterController::class, 'leave'])->name('leave');
        Route::post('/leave/{leave}/decide', [RosterController::class, 'decideLeave'])->name('leave.decide');

        Route::get('/{employee}', [StaffController::class, 'show'])->name('show');
        Route::patch('/{employee}', [StaffController::class, 'update'])->name('update');
    });

    Route::prefix('my-work')->name('my-work.')->group(function (): void {
        Route::get('/', [MyWorkController::class, 'index'])->name('index');
        Route::post('/clock', [MyWorkController::class, 'clock'])->name('clock');
        Route::post('/leave', [MyWorkController::class, 'requestLeave'])->name('leave.store');
        Route::post('/leave/{leave}/cancel', [MyWorkController::class, 'cancelLeave'])->name('leave.cancel');
    });
});
