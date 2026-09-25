<?php

use App\Modules\Crm\Controllers\CrmController;
use Illuminate\Support\Facades\Route;

/* CRM (Phase 21) — `crm` module; crm.view / crm.manage. */

Route::middleware(['auth', 'tenant.context', 'module.active:crm'])
    ->prefix('dashboard/guests')
    ->name('crm.')
    ->group(function (): void {
        Route::get('/', [CrmController::class, 'index'])->name('index');
        Route::post('/', [CrmController::class, 'store'])->name('store');
        Route::get('/{contact}', [CrmController::class, 'show'])->name('show');
        Route::patch('/{contact}', [CrmController::class, 'update'])->name('update');
        Route::post('/{contact}/notes', [CrmController::class, 'note'])->name('notes.store');
        Route::post('/{contact}/interactions', [CrmController::class, 'interaction'])->name('interactions.store');
    });
