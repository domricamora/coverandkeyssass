<?php

use App\Modules\PlatformAdmin\Controllers\ActivityController;
use App\Modules\PlatformAdmin\Controllers\ContentController;
use App\Modules\PlatformAdmin\Controllers\ListingController;
use App\Modules\PlatformAdmin\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/* Super Admin (Phase 28): listings, bookings / orders / payments, pricing, CMS, settings, reports, logs. */

Route::middleware(['auth', 'super.admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/listings/{kind}', [ListingController::class, 'index'])->whereIn('kind', ['properties', 'restaurants'])->name('listings.index');
    Route::post('/listings/{kind}/{id}/status', [ListingController::class, 'status'])->whereIn('kind', ['properties', 'restaurants'])->whereNumber('id')->name('listings.status');

    Route::get('/bookings', [ActivityController::class, 'bookings'])->name('bookings.index');
    Route::get('/orders', [ActivityController::class, 'orders'])->name('orders.index');
    Route::get('/payments', [ActivityController::class, 'payments'])->name('payments.index');
    Route::post('/payments/{payment}/refund', [ActivityController::class, 'refund'])->whereNumber('payment')->name('payments.refund');

    Route::get('/pricing', [ContentController::class, 'pricing'])->name('pricing.index');
    Route::put('/pricing', [ContentController::class, 'savePricing'])->name('pricing.update');
    Route::get('/settings', [ContentController::class, 'settings'])->name('settings.index');
    Route::put('/settings', [ContentController::class, 'saveSettings'])->name('settings.update');

    Route::get('/pages', [ContentController::class, 'pages'])->name('pages.index');
    Route::get('/pages/create', [ContentController::class, 'editPage'])->name('pages.create');
    Route::post('/pages', [ContentController::class, 'savePage'])->name('pages.store');
    Route::get('/pages/{page}/edit', [ContentController::class, 'editPage'])->name('pages.edit');
    Route::put('/pages/{page}', [ContentController::class, 'savePage'])->name('pages.update');
    Route::delete('/pages/{page}', [ContentController::class, 'deletePage'])->name('pages.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}.csv', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/logs', [ReportController::class, 'logs'])->name('logs.index');
});

Route::get('/pages/{slug}', [ContentController::class, 'show'])->where('slug', '[A-Za-z0-9_-]+')->name('pages.show');
