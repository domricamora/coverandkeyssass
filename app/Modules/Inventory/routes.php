<?php

use App\Modules\Inventory\Controllers\InventoryController;
use App\Modules\Inventory\Controllers\PurchaseOrderController;
use App\Modules\Inventory\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

/*
| Inventory (Phase 18) — `inventory` module; inventory.view / manage and
| purchasing.manage. Ids resolve through the tenant scope.
*/

Route::middleware(['auth', 'tenant.context', 'module.active:inventory'])
    ->prefix('dashboard/inventory')
    ->name('inventory.')
    ->group(function (): void {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('/items', [InventoryController::class, 'storeItem'])->name('items.store');
        Route::get('/items/{item}', [InventoryController::class, 'showItem'])->name('items.show');
        Route::patch('/items/{item}', [InventoryController::class, 'updateItem'])->name('items.update');
        Route::post('/items/{item}/move', [InventoryController::class, 'move'])->name('items.move');
        Route::post('/categories', [InventoryController::class, 'storeCategory'])->name('categories.store');
        Route::post('/locations', [InventoryController::class, 'storeLocation'])->name('locations.store');
        Route::post('/suppliers', [InventoryController::class, 'storeSupplier'])->name('suppliers.store');

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/purchase-orders/{po}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::post('/purchase-orders/{po}/order', [PurchaseOrderController::class, 'order'])->name('purchase-orders.order');
        Route::post('/purchase-orders/{po}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{po}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

        Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes');
        Route::post('/recipes/{restaurant}', [RecipeController::class, 'store'])->name('recipes.store');
        Route::post('/recipes/{restaurant}/location', [RecipeController::class, 'setLocation'])->name('recipes.location');
        Route::delete('/recipes/{restaurant}/{ingredient}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
    });
