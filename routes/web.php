<?php

use App\Http\Controllers\Marketing\PageController as MarketingPageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------
// Public marketing site
// ---------------------------------------------------------------------

Route::get('/', [MarketingPageController::class, 'home'])->name('home');
Route::get('/features', [MarketingPageController::class, 'features'])->name('marketing.features');
Route::get('/pricing', [MarketingPageController::class, 'pricing'])->name('marketing.pricing');
Route::get('/contact', [MarketingPageController::class, 'contact'])->name('marketing.contact');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

// ---------------------------------------------------------------------
// Tenant (host) area
// ---------------------------------------------------------------------

Route::get('/tenants', [TenantController::class, 'index'])
    ->middleware('auth')->name('tenants.index');

Route::get('/tenants/create', [TenantController::class, 'create'])
    ->middleware('auth')->name('tenants.create');

Route::post('/tenants', [TenantController::class, 'store'])
    ->middleware('auth')->name('tenants.store');

Route::post('/tenants/{tenant}/switch', [TenantController::class, 'switch'])
    ->middleware('auth')->name('tenants.switch');

Route::middleware(['auth', 'tenant.context'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/team', \App\Http\Livewire\TeamManager::class)->name('team');
    Route::get('/dashboard/settings', [DashboardController::class, 'settings'])->name('tenants.settings');
    Route::patch('/dashboard/settings', [DashboardController::class, 'update'])->name('tenants.update');
});

// ---------------------------------------------------------------------
// Super Admin (platform) area
// ---------------------------------------------------------------------

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super.admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');

    Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/create', [AdminTenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [AdminTenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [AdminTenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{tenant}/suspend', [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/activate', [AdminTenantController::class, 'activate'])->name('tenants.activate');
    Route::delete('/tenants/{tenant}', [AdminTenantController::class, 'destroy'])->name('tenants.destroy');

    // Module engine
    Route::resource('modules', \App\Http\Controllers\Admin\ModuleController::class);
    Route::get('/tenants/{tenant}/modules', [\App\Http\Controllers\Admin\TenantModuleController::class, 'edit'])->name('tenants.modules.edit');
    Route::post('/tenants/{tenant}/modules', [\App\Http\Controllers\Admin\TenantModuleController::class, 'store'])->name('tenants.modules.store');
    Route::delete('/tenants/{tenant}/modules/{module}', [\App\Http\Controllers\Admin\TenantModuleController::class, 'destroy'])->name('tenants.modules.destroy');
});

// ---------------------------------------------------------------------
// Profile (Breeze)
// ---------------------------------------------------------------------

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

