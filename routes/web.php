<?php

use App\Http\Controllers\Inventory\DemandPlanningController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryItemController;
use App\Http\Controllers\Inventory\ProcurementController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StorageLocationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/dashboard', [InventoryController::class, 'index'])->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/inventory', function () {
        return redirect()->route('dashboard');
    })->name('inventory');
    Route::get('/inventory/suppliers', [SupplierController::class, 'index'])->name('inventory.suppliers');
    Route::post('/inventory/suppliers', [SupplierController::class, 'store'])->name('inventory.suppliers.store');
    Route::get('/inventory/items', [InventoryItemController::class, 'index'])->name('inventory.items');
    Route::post('/inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
    Route::get('/inventory/storage-locations', [StorageLocationController::class, 'index'])->name('inventory.storage-locations');
    Route::post('/inventory/storage-locations', [StorageLocationController::class, 'store'])->name('inventory.storage-locations.store');
    Route::get('/inventory/stock-movements', [StockMovementController::class, 'index'])->name('inventory.stock-movements');
    Route::post('/inventory/stock-movements', [StockMovementController::class, 'store'])->name('inventory.stock-movements.store');
    Route::get('/inventory/adjustments', [StockAdjustmentController::class, 'index'])->name('inventory.adjustments');
    Route::post('/inventory/adjustments', [StockAdjustmentController::class, 'store'])->name('inventory.adjustments.store');
    Route::get('/inventory/logistics', [InventoryController::class, 'logistics'])->name('inventory.logistics');
    Route::get('/inventory/purchases', [ProcurementController::class, 'index'])->name('inventory.purchases');
    Route::post('/inventory/purchases/plans', [DemandPlanningController::class, 'store'])->name('inventory.purchases.plans.store');
    Route::post('/inventory/purchases/requests', [ProcurementController::class, 'storeRequest'])->name('inventory.purchases.requests.store');
    Route::post('/inventory/purchases/quotes', [ProcurementController::class, 'storeQuote'])->name('inventory.purchases.quotes.store');
    Route::post('/inventory/purchases/requests/{procurementRequest}/approve', [ProcurementController::class, 'approve'])->name('inventory.purchases.requests.approve');
    Route::post('/inventory/purchases/orders', [PurchaseOrderController::class, 'store'])->name('inventory.purchases.orders.store');
    Route::post('/inventory/purchases/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('inventory.purchases.receive');
    Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
    Route::get('/inventory/alerts', [InventoryController::class, 'alerts'])->name('inventory.alerts');
    Route::get('/inventory/reports', [InventoryController::class, 'reports'])->name('inventory.reports');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
