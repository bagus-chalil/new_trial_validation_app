<?php

use App\Http\Controllers\FillingCheckController;
use App\Http\Controllers\IpcBatchController;
use App\Http\Controllers\PackingCheckController;
use App\Http\Controllers\StartupCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('batches', [IpcBatchController::class, 'index'])->name('batches.index');
    Route::get('batches/create', [IpcBatchController::class, 'create'])->name('batches.create');
    Route::post('batches', [IpcBatchController::class, 'store'])->name('batches.store');
    Route::get('batches/{batch}', [IpcBatchController::class, 'show'])->name('batches.show');

    Route::get('batches/{batch}/startup-check', [StartupCheckController::class, 'edit'])->name('startup-check.edit');
    Route::put('batches/{batch}/startup-check', [StartupCheckController::class, 'update'])->name('startup-check.update');

    Route::get('batches/{batch}/filling-check', [FillingCheckController::class, 'edit'])->name('filling-check.edit');
    Route::put('batches/{batch}/filling-check', [FillingCheckController::class, 'update'])->name('filling-check.update');
    Route::post('batches/{batch}/filling-check/color-photo', [FillingCheckController::class, 'uploadColorPhoto'])->name('filling-check.color-photo');

    Route::get('batches/{batch}/packing-check', [PackingCheckController::class, 'edit'])->name('packing-check.edit');
    Route::put('batches/{batch}/packing-check', [PackingCheckController::class, 'update'])->name('packing-check.update');
});
