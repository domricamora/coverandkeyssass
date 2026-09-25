<?php

use App\Modules\Delivery\Controllers\DeliveryController;
use Illuminate\Support\Facades\Route;

/*
| Delivery setup (Phase 12) under the restaurant area. Driver assignment on
| orders lives with the order queue (Ordering routes).
*/

Route::middleware(['auth', 'tenant.context', 'module.active:restaurant'])
    ->prefix('dashboard/restaurants/{restaurant}/delivery')
    ->name('restaurants.delivery.')
    ->group(function (): void {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::patch('/settings', [DeliveryController::class, 'updateSettings'])->name('settings');
        Route::post('/zones', [DeliveryController::class, 'storeZone'])->name('zones.store');
        Route::post('/zones/{zone}/toggle', [DeliveryController::class, 'toggleZone'])->name('zones.toggle');
        Route::delete('/zones/{zone}', [DeliveryController::class, 'destroyZone'])->name('zones.destroy');
        Route::post('/drivers', [DeliveryController::class, 'storeDriver'])->name('drivers.store');
        Route::post('/drivers/{driver}/toggle', [DeliveryController::class, 'toggleDriver'])->name('drivers.toggle');
    });
