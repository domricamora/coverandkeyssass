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
Route::get('/llms.txt', [SeoController::class, 'llms'])->name('seo.llms');

// Sign-in return for guest flows: signed-out guests are sent to log in or
// register and come back to the booking / cart / table step they left.
// Only same-site relative paths are followed.
Route::get('/continue', fn (\Illuminate\Http\Request $request) => redirect(\App\Http\Controllers\GuestCheckoutController::safePath((string) $request->query('to'))))
    ->middleware('auth')->name('continue');

// Checkout without registration (see GuestCheckoutController).
Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('/guest/identify', [\App\Http\Controllers\GuestCheckoutController::class, 'identify'])->name('guest.identify');
    Route::post('/guest/password', [\App\Http\Controllers\GuestCheckoutController::class, 'password'])->name('guest.password');
});
Route::post('/login-link', [\App\Http\Controllers\GuestCheckoutController::class, 'sendLink'])->middleware('throttle:5,10')->name('login.link.send');
Route::get('/login-link/{user}', [\App\Http\Controllers\GuestCheckoutController::class, 'useLink'])->middleware('signed')->name('login.link');

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
    Route::get('/dashboard/team', [\App\Http\Controllers\TeamController::class, 'index'])->name('team');
    Route::post('/dashboard/team', [\App\Http\Controllers\TeamController::class, 'store'])->name('team.store');
    Route::patch('/dashboard/team/{user}', [\App\Http\Controllers\TeamController::class, 'role'])->whereNumber('user')->name('team.role');
    Route::delete('/dashboard/team/{user}', [\App\Http\Controllers\TeamController::class, 'destroy'])->whereNumber('user')->name('team.remove');

    // Room-type galleries, dish photos and menu section banners (PhotoController).
    Route::prefix('/dashboard/photos/{type}/{id}')->whereIn('type', ['room-type', 'menu-item', 'menu-category'])->whereNumber('id')->name('photos.')->group(function (): void {
        Route::post('/', [\App\Http\Controllers\PhotoController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::post('/{media}/cover', [\App\Http\Controllers\PhotoController::class, 'cover'])->whereNumber('media')->name('cover');
        Route::delete('/{media}', [\App\Http\Controllers\PhotoController::class, 'destroy'])->whereNumber('media')->name('destroy');
    });
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

