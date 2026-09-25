<?php

use App\Modules\RestaurantManagement\Controllers\MenuController;
use App\Modules\RestaurantManagement\Controllers\RestaurantController;
use App\Modules\RestaurantManagement\Controllers\TableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restaurant Management routes (host side, Phase 09)
|--------------------------------------------------------------------------
|
| Requires an authenticated user, a resolved tenant context and an active
| `restaurant` module. Permissions are checked per action (restaurants.*,
| menu.*, tables.*). Parameters are resolved manually through the
| tenant-scoped restaurant — see RestaurantManagementController.
|
*/

Route::middleware(['auth', 'tenant.context', 'module.active:restaurant'])
    ->prefix('dashboard/restaurants')
    ->name('restaurants.')
    ->group(function (): void {
        Route::get('/', [RestaurantController::class, 'index'])->name('index');
        Route::get('/create', [RestaurantController::class, 'create'])->name('create');
        Route::post('/', [RestaurantController::class, 'store'])->name('store');

        Route::prefix('{restaurant}')->group(function (): void {
            Route::get('/', [RestaurantController::class, 'show'])->name('show');
            Route::get('/edit', [RestaurantController::class, 'edit'])->name('edit');
            Route::patch('/', [RestaurantController::class, 'update'])->name('update');
            Route::delete('/', [RestaurantController::class, 'destroy'])->name('destroy');
            Route::post('/publish', [RestaurantController::class, 'publish'])->name('publish');
            Route::post('/unpublish', [RestaurantController::class, 'unpublish'])->name('unpublish');

            Route::post('/media', [RestaurantController::class, 'addMedia'])->name('media.store');
            Route::delete('/media/{media}', [RestaurantController::class, 'destroyMedia'])->name('media.destroy');
            Route::post('/media/{media}/cover', [RestaurantController::class, 'setCover'])->name('media.cover');

            // Menu builder.
            Route::get('/menu', [MenuController::class, 'index'])->name('menu');
            Route::post('/menu/categories', [MenuController::class, 'storeCategory'])->name('categories.store');
            Route::patch('/menu/categories/{category}', [MenuController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/menu/categories/{category}', [MenuController::class, 'destroyCategory'])->name('categories.destroy');
            Route::post('/menu/items', [MenuController::class, 'storeItem'])->name('items.store');
            Route::patch('/menu/items/{item}', [MenuController::class, 'updateItem'])->name('items.update');
            Route::delete('/menu/items/{item}', [MenuController::class, 'destroyItem'])->name('items.destroy');
            Route::post('/menu/items/{item}/groups', [MenuController::class, 'storeGroup'])->name('groups.store');
            Route::delete('/menu/items/{item}/groups/{group}', [MenuController::class, 'destroyGroup'])->name('groups.destroy');
            Route::post('/menu/items/{item}/groups/{group}/options', [MenuController::class, 'storeOption'])->name('options.store');
            Route::delete('/menu/items/{item}/groups/{group}/options/{option}', [MenuController::class, 'destroyOption'])->name('options.destroy');

            // Floor plan.
            Route::get('/tables', [TableController::class, 'index'])->name('tables');
            Route::post('/areas', [TableController::class, 'storeArea'])->name('areas.store');
            Route::delete('/areas/{area}', [TableController::class, 'destroyArea'])->name('areas.destroy');
            Route::post('/tables', [TableController::class, 'storeTable'])->name('tables.store');
            Route::patch('/tables/{table}', [TableController::class, 'updateTable'])->name('tables.update');
            Route::delete('/tables/{table}', [TableController::class, 'destroyTable'])->name('tables.destroy');
        });
    });
