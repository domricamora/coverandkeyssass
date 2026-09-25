<?php

use App\Modules\Marketing\Controllers\MarketingController;
use App\Modules\Marketing\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
| Marketing (Phase 22) — part of the `crm` module ("Loyalty & campaigns");
| marketing.view / marketing.manage. The unsubscribe link is public and signed.
*/

Route::middleware(['auth', 'tenant.context', 'module.active:crm'])
    ->prefix('dashboard/marketing')
    ->name('marketing.')
    ->group(function (): void {
        Route::get('/', [MarketingController::class, 'index'])->name('index');
        Route::post('/campaigns', [MarketingController::class, 'storeCampaign'])->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [MarketingController::class, 'showCampaign'])->name('campaigns.show');
        Route::post('/campaigns/{campaign}/send', [MarketingController::class, 'sendCampaign'])->name('campaigns.send');
        Route::post('/campaigns/{campaign}/schedule', [MarketingController::class, 'scheduleCampaign'])->name('campaigns.schedule');
        Route::patch('/automations/{type}', [MarketingController::class, 'updateAutomation'])->name('automations.update');
    });

Route::get('/unsubscribe/{tenant}/{contact}', UnsubscribeController::class)->middleware('throttle:30,1')->name('marketing.unsubscribe');
