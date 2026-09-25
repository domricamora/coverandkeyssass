<?php

use App\Modules\Reviews\Controllers\AdminReviewController;
use App\Modules\Reviews\Controllers\HostReviewController;
use Illuminate\Support\Facades\Route;

/* Reviews (Phase 24): host screen (reviews.view / reply) and Super Admin moderation. */

Route::middleware(['auth', 'tenant.context'])
    ->prefix('dashboard/reviews')
    ->name('reviews.')
    ->group(function (): void {
        Route::get('/', [HostReviewController::class, 'index'])->name('index');
        Route::post('/{review}/reply', [HostReviewController::class, 'reply'])->name('reply');
        Route::post('/{review}/flag', [HostReviewController::class, 'flag'])->name('flag');
    });

Route::middleware(['auth', 'super.admin'])
    ->prefix('admin/reviews')
    ->name('admin.reviews.')
    ->group(function (): void {
        Route::get('/', [AdminReviewController::class, 'index'])->name('index');
        Route::post('/{review}/moderate', [AdminReviewController::class, 'moderate'])->name('moderate');
    });
