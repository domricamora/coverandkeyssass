<?php

use App\Modules\Loyalty\Controllers\LoyaltyController;
use App\Modules\Loyalty\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

/* Loyalty (Phase 23) — part of the `crm` module; loyalty.view / loyalty.manage. */

Route::middleware(['auth', 'tenant.context', 'module.active:crm'])
    ->prefix('dashboard/loyalty')
    ->name('loyalty.')
    ->group(function (): void {
        Route::get('/', [LoyaltyController::class, 'index'])->name('index');
        Route::patch('/program', [LoyaltyController::class, 'updateProgram'])->name('program');
        Route::post('/rewards', [LoyaltyController::class, 'storeReward'])->name('rewards.store');
        Route::post('/rewards/{reward}/toggle', [LoyaltyController::class, 'toggleReward'])->name('rewards.toggle');
        Route::post('/contacts/{contact}/enroll', [LoyaltyController::class, 'enroll'])->name('enroll');
        Route::get('/members/{account}', [LoyaltyController::class, 'member'])->name('members.show');
        Route::post('/members/{account}/redeem', [LoyaltyController::class, 'redeem'])->name('members.redeem');
        Route::post('/members/{account}/adjust', [LoyaltyController::class, 'adjust'])->name('members.adjust');
        Route::post('/gift-cards', [LoyaltyController::class, 'sellGiftCard'])->name('gift-cards.store');
        Route::post('/gift-cards/{card}/void', [LoyaltyController::class, 'voidGiftCard'])->name('gift-cards.void');
    });

Route::middleware('auth')->group(function (): void {
    Route::get('/account/loyalty', [MemberController::class, 'index'])->name('account.loyalty');
    Route::post('/account/loyalty/{account}/referral', [MemberController::class, 'referral'])->name('account.loyalty.referral');
});
