<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\ProjectQuotationController;
use App\Http\Controllers\ProjectStageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('projects')->as('projects.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('create', [ProjectController::class, 'create'])->name('create');
    Route::post('/', [ProjectController::class, 'store'])->name('store');

    // Cross-project Quotation & PO list; declared before {project} routes.
    Route::get('quotations', [ProjectQuotationController::class, 'index'])->name('quotations.index');

    Route::where(['project' => '[0-9]+', 'stage' => '[0-9]+', 'milestone' => '[0-9]+', 'quotation' => '[0-9]+', 'file' => '[0-9]+'])->group(function () {
        Route::get('{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('{project}', [ProjectController::class, 'destroy'])->name('destroy');

        Route::post('{project}/stages', [ProjectStageController::class, 'store'])->name('stages.store');
        Route::put('{project}/stages/{stage}', [ProjectStageController::class, 'update'])->name('stages.update');
        Route::delete('{project}/stages/{stage}', [ProjectStageController::class, 'destroy'])->name('stages.destroy');

        Route::post('{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('milestones.store');
        Route::put('{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('milestones.destroy');

        Route::post('{project}/quotations', [ProjectQuotationController::class, 'store'])->name('quotations.store');
        Route::put('{project}/quotations/{quotation}', [ProjectQuotationController::class, 'update'])->name('quotations.update');
        Route::delete('{project}/quotations/{quotation}', [ProjectQuotationController::class, 'destroy'])->name('quotations.destroy');

        Route::post('{project}/files', [ProjectFileController::class, 'store'])->name('files.store');
        Route::get('{project}/files/{file}', [ProjectFileController::class, 'show'])->name('files.show');
        Route::delete('{project}/files/{file}', [ProjectFileController::class, 'destroy'])->name('files.destroy');
    });
});
