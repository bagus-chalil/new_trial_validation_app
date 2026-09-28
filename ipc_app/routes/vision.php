<?php

use App\Http\Controllers\VisionController;
use App\Http\Controllers\VisionTestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post('batches/{batch}/vision/analyze', [VisionController::class, 'analyze'])
        ->middleware('can:update,batch')
        ->name('vision.analyze');
    Route::get('batches/{batch}/vision/logs', [VisionController::class, 'index'])->name('vision.logs');
});

// "OCR Test Product" sandbox — no batch, nothing persisted (see VisionTestController).
Route::middleware(['auth', 'can:use-vision-test'])->group(function () {
    Route::get('vision/test', [VisionTestController::class, 'index'])->name('vision-test.index');
    Route::post('vision/test/analyze', [VisionTestController::class, 'analyze'])->name('vision-test.analyze');
});
