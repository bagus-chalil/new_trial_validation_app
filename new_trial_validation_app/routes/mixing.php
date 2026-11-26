<?php

use App\Http\Controllers\MixingController;
use App\Http\Controllers\MixingStepController;
use Illuminate\Support\Facades\Route;

// Trial Validation v2.0 Tahap 4: Mixing (Case kind MIX).
Route::middleware(['auth', 'verified'])->prefix('mixings')->as('mixings.')->group(function () {
    Route::get('/', [MixingController::class, 'index'])->name('index');
    Route::get('create', [MixingController::class, 'create'])->name('create');
    Route::post('/', [MixingController::class, 'store'])->name('store');

    Route::get('{mixing}', [MixingController::class, 'show'])->whereNumber('mixing')->name('show');
    Route::get('{mixing}/edit', [MixingController::class, 'edit'])->whereNumber('mixing')->name('edit');
    Route::put('{mixing}', [MixingController::class, 'update'])->whereNumber('mixing')->name('update');
    Route::delete('{mixing}', [MixingController::class, 'destroy'])->whereNumber('mixing')->name('destroy');

    Route::get('{mixing}/validation', [MixingStepController::class, 'validationEdit'])->whereNumber('mixing')->name('validation.edit');
    Route::put('{mixing}/validation', [MixingStepController::class, 'validationUpdate'])->whereNumber('mixing')->name('validation.update');

    Route::get('{mixing}/attachments', [MixingStepController::class, 'attachmentsEdit'])->whereNumber('mixing')->name('attachments.edit');
    Route::post('{mixing}/attachments', [MixingStepController::class, 'attachmentsStore'])->whereNumber('mixing')->name('attachments.store');
    Route::delete('{mixing}/attachments/{file}', [MixingStepController::class, 'attachmentsDestroy'])
        ->whereNumber('mixing')->whereNumber('file')->name('attachments.destroy');
    Route::get('{mixing}/files/{file}', [MixingStepController::class, 'fileShow'])
        ->whereNumber('mixing')->whereNumber('file')->name('files.show');

    Route::get('{mixing}/review', [MixingStepController::class, 'reviewEdit'])->whereNumber('mixing')->name('review.edit');
    Route::post('{mixing}/review', [MixingStepController::class, 'submit'])->whereNumber('mixing')->name('review.submit');
    Route::post('reviews/{review}', [MixingStepController::class, 'reviewSave'])->whereNumber('review')->name('review.save');
    Route::post('{mixing}/decision', [MixingStepController::class, 'decide'])->whereNumber('mixing')->name('decision');
});
