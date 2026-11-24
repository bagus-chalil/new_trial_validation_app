<?php

use App\Http\Controllers\ImprovementController;
use App\Http\Controllers\ImprovementFileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProjectGanttController;
use App\Http\Controllers\ProjectMilestoneController;
use App\Http\Controllers\ProjectPurchaseOrderController;
use App\Http\Controllers\ProjectQuotationController;
use App\Http\Controllers\ProjectStageController;
use App\Http\Controllers\ProjectStageTrialController;
use App\Http\Controllers\ToolingController;
use App\Http\Controllers\ToolingFileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('projects')->as('projects.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('create', [ProjectController::class, 'create'])->name('create');
    Route::post('/', [ProjectController::class, 'store'])->name('store');

    // Cross-project Quotation & PO list; declared before {project} routes.
    Route::get('quotations', [ProjectQuotationController::class, 'index'])->name('quotations.index');

    // Purchase Order documents (FR.PRC.001.00); literal paths, declared before {project} routes.
    Route::prefix('purchase-orders')->as('purchase-orders.')->where(['purchaseOrder' => '[0-9]+'])->group(function () {
        Route::get('/', [ProjectPurchaseOrderController::class, 'index'])->name('index');
        Route::get('template', [ProjectPurchaseOrderController::class, 'template'])->name('template');
        Route::put('template', [ProjectPurchaseOrderController::class, 'updateTemplate'])->name('template.update');
        Route::get('{purchaseOrder}', [ProjectPurchaseOrderController::class, 'show'])->name('show');
        Route::get('{purchaseOrder}/pdf', [ProjectPurchaseOrderController::class, 'pdf'])->name('pdf');
        Route::get('{purchaseOrder}/edit', [ProjectPurchaseOrderController::class, 'edit'])->name('edit');
        Route::put('{purchaseOrder}', [ProjectPurchaseOrderController::class, 'update'])->name('update');
        Route::delete('{purchaseOrder}', [ProjectPurchaseOrderController::class, 'destroy'])->name('destroy');
    });

    Route::get('gantt', [ProjectGanttController::class, 'index'])->name('gantt');

    Route::prefix('improvements')->as('improvements.')->where(['improvement' => '[0-9]+', 'file' => '[0-9]+'])->group(function () {
        Route::get('/', [ImprovementController::class, 'index'])->name('index');
        Route::post('/', [ImprovementController::class, 'store'])->name('store');
        Route::get('{improvement}', [ImprovementController::class, 'show'])->name('show');
        Route::put('{improvement}', [ImprovementController::class, 'update'])->name('update');
        Route::post('{improvement}/review', [ImprovementController::class, 'review'])->name('review');
        Route::post('{improvement}/convert', [ImprovementController::class, 'convert'])->name('convert');
        Route::delete('{improvement}', [ImprovementController::class, 'destroy'])->name('destroy');

        Route::post('{improvement}/files', [ImprovementFileController::class, 'store'])->name('files.store');
        Route::get('{improvement}/files/{file}', [ImprovementFileController::class, 'show'])->name('files.show');
        Route::delete('{improvement}/files/{file}', [ImprovementFileController::class, 'destroy'])->name('files.destroy');
    });

    // Check Tooling (FR.PRNI.002.00), PROJECT_MODULE_PLAN.md Fase 4b.
    Route::prefix('toolings')->as('toolings.')->where(['tooling' => '[0-9]+', 'file' => '[0-9]+'])->group(function () {
        Route::get('/', [ToolingController::class, 'index'])->name('index');
        Route::get('create', [ToolingController::class, 'create'])->name('create');
        Route::post('/', [ToolingController::class, 'store'])->name('store');
        Route::get('{tooling}', [ToolingController::class, 'show'])->name('show');
        Route::get('{tooling}/edit', [ToolingController::class, 'edit'])->name('edit');
        Route::put('{tooling}', [ToolingController::class, 'update'])->name('update');
        Route::delete('{tooling}', [ToolingController::class, 'destroy'])->name('destroy');
        Route::post('{tooling}/steps/{step}', [ToolingController::class, 'step'])
            ->whereIn('step', ['sign-prni', 'send-sample', 'sign-rni', 'receive-sample', 'pie-check', 'sign-production', 'hold', 'reject', 'resume'])
            ->name('steps');
        Route::post('{tooling}/promote', [ToolingController::class, 'promote'])->name('promote');

        Route::post('{tooling}/files', [ToolingFileController::class, 'store'])->name('files.store');
        Route::get('{tooling}/files/{file}', [ToolingFileController::class, 'show'])->name('files.show');
        Route::delete('{tooling}/files/{file}', [ToolingFileController::class, 'destroy'])->name('files.destroy');
    });

    Route::where(['project' => '[0-9]+', 'stage' => '[0-9]+', 'milestone' => '[0-9]+', 'quotation' => '[0-9]+', 'file' => '[0-9]+'])->group(function () {
        Route::get('{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('{project}', [ProjectController::class, 'destroy'])->name('destroy');

        Route::post('{project}/stages', [ProjectStageController::class, 'store'])->name('stages.store');
        Route::put('{project}/stages/{stage}', [ProjectStageController::class, 'update'])->name('stages.update');
        Route::delete('{project}/stages/{stage}', [ProjectStageController::class, 'destroy'])->name('stages.destroy');
        Route::post('{project}/stages/{stage}/trial', [ProjectStageTrialController::class, 'store'])->name('stages.trial.store');
        Route::delete('{project}/stages/{stage}/trial', [ProjectStageTrialController::class, 'destroy'])->name('stages.trial.destroy');

        Route::post('{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('milestones.store');
        Route::put('{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy'])->name('milestones.destroy');

        Route::post('{project}/quotations', [ProjectQuotationController::class, 'store'])->name('quotations.store');
        Route::put('{project}/quotations/{quotation}', [ProjectQuotationController::class, 'update'])->name('quotations.update');
        Route::delete('{project}/quotations/{quotation}', [ProjectQuotationController::class, 'destroy'])->name('quotations.destroy');

        Route::get('{project}/purchase-orders/create', [ProjectPurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('{project}/purchase-orders', [ProjectPurchaseOrderController::class, 'store'])->name('purchase-orders.store');

        Route::post('{project}/files', [ProjectFileController::class, 'store'])->name('files.store');
        Route::get('{project}/files/{file}', [ProjectFileController::class, 'show'])->name('files.show');
        Route::delete('{project}/files/{file}', [ProjectFileController::class, 'destroy'])->name('files.destroy');
    });
});
