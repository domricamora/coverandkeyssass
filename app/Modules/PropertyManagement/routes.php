<?php

use App\Modules\PropertyManagement\Controllers\AvailabilityController;
use App\Modules\PropertyManagement\Controllers\InventoryController;
use App\Modules\PropertyManagement\Controllers\PropertyController;
use App\Modules\PropertyManagement\Controllers\RateController;
use App\Modules\PropertyManagement\Controllers\RoomController;
use App\Modules\PropertyManagement\Controllers\RoomTypeController;
use App\Modules\PropertyManagement\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Property Management routes (host side, Phase 04)
|--------------------------------------------------------------------------
|
| Loaded by PropertyManagementServiceProvider inside the `web` group.
| Every route requires: an authenticated user with a resolved tenant
| context AND an active `property` module for that tenant. Permissions are
| enforced per-action in the controllers (properties.*, rooms.*, rates.*,
| availability.*, properties.staff.manage).
|
| NOTE: {property} and nested parameters are intentionally NOT bound to
| models. Laravel's SubstituteBindings runs before the tenant.context
| middleware, so implicit binding would resolve rows without the tenant
| scope. Controllers resolve every parameter manually through tenant-scoped
| relations (see PropertyManagementController) — foreign rows are a 404.
|
*/

Route::middleware(['auth', 'tenant.context', 'module.active:property'])
    ->prefix('dashboard/properties')
    ->name('properties.')
    ->group(function (): void {
        Route::get('/', [PropertyController::class, 'index'])->name('index');
        Route::get('/create', [PropertyController::class, 'create'])->name('create');
        Route::post('/', [PropertyController::class, 'store'])->name('store');

        Route::prefix('{property}')->group(function (): void {
            Route::get('/', [PropertyController::class, 'show'])->name('show');
            Route::get('/edit', [PropertyController::class, 'edit'])->name('edit');
            Route::patch('/', [PropertyController::class, 'update'])->name('update');
            Route::delete('/', [PropertyController::class, 'destroy'])->name('destroy');

            Route::post('/publish', [PropertyController::class, 'publish'])->name('publish');
            Route::post('/unpublish', [PropertyController::class, 'unpublish'])->name('unpublish');

            // Media (photos + videos) — reached only through the property.
            Route::post('/media', [PropertyController::class, 'addMedia'])->name('media.store');
            Route::delete('/media/{media}', [PropertyController::class, 'destroyMedia'])->name('media.destroy');
            Route::post('/media/{media}/cover', [PropertyController::class, 'setCover'])->name('media.cover');

            // Inventory workbench.
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');

            Route::post('/room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
            Route::patch('/room-types/{room_type}', [RoomTypeController::class, 'update'])->name('room-types.update');
            Route::delete('/room-types/{room_type}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');

            Route::post('/room-types/{room_type}/rooms', [RoomController::class, 'store'])->name('rooms.store');
            Route::patch('/room-types/{room_type}/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
            Route::delete('/room-types/{room_type}/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

            Route::post('/room-types/{room_type}/rates', [RateController::class, 'store'])->name('rates.store');
            Route::delete('/room-types/{room_type}/rates/{rate_period}', [RateController::class, 'destroy'])->name('rates.destroy');

            Route::post('/availability', [AvailabilityController::class, 'store'])->name('availability.store');
            Route::delete('/availability/{availability_block}', [AvailabilityController::class, 'destroy'])->name('availability.destroy');

            // Property staff assignments.
            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
            Route::patch('/staff/{property_staff}', [StaffController::class, 'update'])->name('staff.update');
            Route::delete('/staff/{property_staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
        });
    });
