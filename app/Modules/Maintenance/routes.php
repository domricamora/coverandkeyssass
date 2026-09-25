<?php

use App\Modules\Maintenance\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

/*
| Maintenance (Phase 16) — `workforce` module; maintenance.view / work /
| manage. {ticket} is the reference, resolved through the tenant scope.
*/

Route::middleware(['auth', 'tenant.context', 'module.active:workforce'])
    ->prefix('dashboard/maintenance')
    ->name('maintenance.')
    ->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');
        Route::post('/', [MaintenanceController::class, 'store'])->name('store');
        Route::get('/{ticket}', [MaintenanceController::class, 'show'])->name('show');
        Route::post('/{ticket}/assign', [MaintenanceController::class, 'assign'])->name('assign');
        Route::post('/{ticket}/status', [MaintenanceController::class, 'transition'])->name('transition');
        Route::post('/{ticket}/cost', [MaintenanceController::class, 'cost'])->name('cost');
        Route::post('/{ticket}/notes', [MaintenanceController::class, 'note'])->name('notes.store');
        Route::post('/{ticket}/attachments', [MaintenanceController::class, 'attach'])->name('attachments.store');
        Route::get('/{ticket}/attachments/{media}', [MaintenanceController::class, 'download'])->name('attachments.show');
    });
