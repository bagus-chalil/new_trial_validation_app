<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\FillingCheckController;
use App\Http\Controllers\FinishedCheckController;
use App\Http\Controllers\IpcBatchController;
use App\Http\Controllers\PackingCheckController;
use App\Http\Controllers\StartupCheckController;
use App\Http\Controllers\StartupInspectionController;
use App\Models\IpcApproval;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('batches', [IpcBatchController::class, 'index'])->name('batches.index');
    Route::get('batches/create', [IpcBatchController::class, 'create'])->name('batches.create');
    Route::post('batches', [IpcBatchController::class, 'store'])->name('batches.store');
    Route::get('batches/{batch}', [IpcBatchController::class, 'show'])->name('batches.show');

    Route::get('batches/{batch}/startup-check', [StartupCheckController::class, 'edit'])->name('startup-check.edit');
    Route::put('batches/{batch}/startup-check', [StartupCheckController::class, 'update'])->name('startup-check.update');
    Route::post('batches/{batch}/startup-check/photo/{field}', [StartupCheckController::class, 'uploadPhoto'])
        ->whereIn('field', StartupCheckController::PHOTO_FIELDS)
        ->name('startup-check.photo');

    Route::get('batches/{batch}/startup-inspection', [StartupInspectionController::class, 'edit'])->name('startup-inspection.edit');
    Route::put('batches/{batch}/startup-inspection', [StartupInspectionController::class, 'update'])->name('startup-inspection.update');

    Route::get('batches/{batch}/filling-check', [FillingCheckController::class, 'edit'])->name('filling-check.edit');
    Route::put('batches/{batch}/filling-check', [FillingCheckController::class, 'update'])->name('filling-check.update');
    Route::post('batches/{batch}/filling-check/color-photo', [FillingCheckController::class, 'uploadColorPhoto'])->name('filling-check.color-photo');

    Route::get('batches/{batch}/packing-check', [PackingCheckController::class, 'edit'])->name('packing-check.edit');
    Route::put('batches/{batch}/packing-check', [PackingCheckController::class, 'update'])->name('packing-check.update');
    Route::post('batches/{batch}/packing-check/photo/{field}', [PackingCheckController::class, 'uploadPhoto'])
        ->whereIn('field', PackingCheckController::PHOTO_FIELDS)
        ->name('packing-check.photo');

    Route::get('batches/{batch}/finished-check', [FinishedCheckController::class, 'edit'])->name('finished-check.edit');
    Route::put('batches/{batch}/finished-check', [FinishedCheckController::class, 'update'])->name('finished-check.update');
    Route::post('batches/{batch}/finished-check/photo/{field}', [FinishedCheckController::class, 'uploadPhoto'])
        ->whereIn('field', FinishedCheckController::PHOTO_FIELDS)
        ->name('finished-check.photo');

    Route::get('batches/{batch}/approval', [ApprovalController::class, 'edit'])->name('approval.edit');
    Route::get('batches/{batch}/approval/startup', [ApprovalController::class, 'startup'])->name('approval.startup');
    Route::get('batches/{batch}/approval/filling-packing', [ApprovalController::class, 'fillingPacking'])->name('approval.filling-packing');
    Route::get('batches/{batch}/approval/finished', [ApprovalController::class, 'finished'])->name('approval.finished');
    Route::put('batches/{batch}/approval/{stage}', [ApprovalController::class, 'update'])
        ->whereIn('stage', IpcApproval::STAGES)
        ->name('approval.update');
    Route::get('batches/{batch}/approval/{stage}/print', [ApprovalController::class, 'print'])
        ->whereIn('stage', IpcApproval::STAGES)
        ->name('approval.print');
});
