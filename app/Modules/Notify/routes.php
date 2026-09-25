<?php

use App\Modules\Notify\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/* Notify (Phase 26): staff notification centre, channel preferences, push devices. */

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/dashboard/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/account/notification-settings', [NotificationController::class, 'settings'])->name('account.notification-settings');
    Route::put('/account/notification-settings', [NotificationController::class, 'saveSettings'])->name('account.notification-settings.update');

    Route::post('/account/push-devices', [NotificationController::class, 'registerDevice'])->middleware('throttle:20,1')->name('account.push-devices.store');
    Route::delete('/account/push-devices', [NotificationController::class, 'removeDevice'])->name('account.push-devices.destroy');
});
