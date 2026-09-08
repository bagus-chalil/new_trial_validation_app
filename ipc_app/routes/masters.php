<?php

use App\Http\Controllers\MasterLineController;
use App\Http\Controllers\MasterProductBulkCodeController;
use App\Http\Controllers\MasterProductController;
use App\Http\Controllers\MasterTestTypeController;
use App\Http\Controllers\RecycleBinController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('masters')->group(function () {
    Route::get('lines', [MasterLineController::class, 'index'])->name('master-lines.index');
    Route::post('lines', [MasterLineController::class, 'store'])->name('master-lines.store');
    Route::delete('lines/{masterLine}', [MasterLineController::class, 'destroy'])->name('master-lines.destroy');

    Route::get('products', [MasterProductController::class, 'index'])->name('master-products.index');
    Route::post('products', [MasterProductController::class, 'store'])->name('master-products.store');
    Route::delete('products/{masterProduct}', [MasterProductController::class, 'destroy'])->name('master-products.destroy');

    Route::post('products/{masterProduct}/bulk-codes', [MasterProductBulkCodeController::class, 'store'])->name('master-product-bulk-codes.store');
    Route::delete('products/{masterProduct}/bulk-codes/{bulkCode}', [MasterProductBulkCodeController::class, 'destroy'])->name('master-product-bulk-codes.destroy');

    Route::get('test-types', [MasterTestTypeController::class, 'index'])->name('master-test-types.index');
    Route::post('test-types', [MasterTestTypeController::class, 'store'])->name('master-test-types.store');
    Route::delete('test-types/{masterTestType}', [MasterTestTypeController::class, 'destroy'])->name('master-test-types.destroy');

    Route::get('recycle-bin', [RecycleBinController::class, 'index'])->name('recycle-bin.index');
    Route::patch('recycle-bin/batches/{id}/restore', [RecycleBinController::class, 'restoreBatch'])->name('recycle-bin.batches.restore');
    Route::patch('recycle-bin/products/{id}/restore', [RecycleBinController::class, 'restoreProduct'])->name('recycle-bin.products.restore');
    Route::patch('recycle-bin/lines/{id}/restore', [RecycleBinController::class, 'restoreLine'])->name('recycle-bin.lines.restore');
    Route::patch('recycle-bin/test-types/{id}/restore', [RecycleBinController::class, 'restoreTestType'])->name('recycle-bin.test-types.restore');
});
