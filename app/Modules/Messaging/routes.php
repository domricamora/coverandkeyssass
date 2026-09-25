<?php

use App\Modules\Messaging\Controllers\BusinessMessageController;
use App\Modules\Messaging\Controllers\GuestMessageController;
use App\Modules\Messaging\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

/* Messaging (Phase 25): guest inbox, business inbox, platform support, attachments. */

Route::middleware('auth')->group(function (): void {
    Route::prefix('account/messages')->name('account.messages.')->group(function (): void {
        Route::get('/', [GuestMessageController::class, 'index'])->name('index');
        Route::get('/new', [GuestMessageController::class, 'create'])->name('create');
        Route::post('/', [GuestMessageController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{thread}', [GuestMessageController::class, 'show'])->name('show');
        Route::post('/{thread}/reply', [GuestMessageController::class, 'reply'])->middleware('throttle:30,1')->name('reply');
        Route::post('/{thread}/close', [GuestMessageController::class, 'close'])->name('close');
    });

    Route::get('/messages/{thread}/attachments/{media}', [SupportController::class, 'attachment'])->name('messages.attachment');
});

Route::middleware(['auth', 'tenant.context'])->prefix('dashboard/messages')->name('messages.')->group(function (): void {
    Route::get('/', [BusinessMessageController::class, 'index'])->name('index');
    Route::post('/staff', [BusinessMessageController::class, 'storeStaff'])->name('staff.store');
    Route::get('/{thread}', [BusinessMessageController::class, 'show'])->name('show');
    Route::post('/{thread}/reply', [BusinessMessageController::class, 'reply'])->name('reply');
    Route::post('/{thread}/status', [BusinessMessageController::class, 'status'])->name('status');
});

Route::middleware(['auth', 'super.admin'])->prefix('admin/support')->name('admin.support.')->group(function (): void {
    Route::get('/', [SupportController::class, 'index'])->name('index');
    Route::get('/{thread}', [SupportController::class, 'show'])->name('show');
    Route::post('/{thread}/reply', [SupportController::class, 'reply'])->name('reply');
    Route::post('/{thread}/status', [SupportController::class, 'status'])->name('status');
});
