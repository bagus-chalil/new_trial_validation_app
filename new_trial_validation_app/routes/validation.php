<?php

use App\Http\Controllers\ValidationAssessmentController;
use App\Http\Controllers\ValidationCaseImprovementController;
use App\Http\Controllers\ValidationCaseNumberController;
use App\Http\Controllers\ValidationCaseToolingController;
use App\Http\Controllers\ValidationImController;
use App\Http\Controllers\ValidationJourneyController;
use App\Http\Controllers\ValidationPlanController;
use App\Http\Controllers\ValidationQcoController;
use Illuminate\Support\Facades\Route;

// Trial Validation v2.0: Product Journey per Case.
Route::middleware(['auth', 'verified'])->prefix('journey')->as('journey.')->group(function () {
    Route::get('/', [ValidationJourneyController::class, 'index'])->name('index');
    Route::get('{case}', [ValidationJourneyController::class, 'show'])->whereNumber('case')->name('show');
    Route::post('{case}/toolings', [ValidationCaseToolingController::class, 'store'])->whereNumber('case')->name('toolings.store');
    Route::delete('{case}/toolings/{tooling}', [ValidationCaseToolingController::class, 'destroy'])
        ->whereNumber('case')->whereNumber('tooling')->name('toolings.destroy');
    Route::post('{case}/improvements', [ValidationCaseImprovementController::class, 'store'])->whereNumber('case')->name('improvements.store');
    Route::delete('{case}/improvements/{improvement}', [ValidationCaseImprovementController::class, 'destroy'])
        ->whereNumber('case')->whereNumber('improvement')->name('improvements.destroy');
    Route::post('{case}/numbers', [ValidationCaseNumberController::class, 'store'])->whereNumber('case')->name('numbers.store');
    Route::delete('{case}/numbers/{kind}/{number}', [ValidationCaseNumberController::class, 'destroy'])
        ->whereNumber('case')->whereNumber('number')->whereIn('kind', ['qco', 'im'])->name('numbers.destroy');
    Route::post('{case}/trials/{trial}/client-approval', [ValidationJourneyController::class, 'clientApproval'])
        ->whereNumber('case')->whereNumber('trial')->name('client-approval');
});

// Trial Validation v2.0 Tahap 2: Validation Assessment register and weekly Validation Plan.
Route::middleware(['auth', 'verified'])->prefix('validation')->as('validation.')->group(function () {
    Route::get('assessments', [ValidationAssessmentController::class, 'index'])->name('assessments.index');
    Route::post('assessments', [ValidationAssessmentController::class, 'store'])->name('assessments.store');
    Route::put('assessments/{assessment}', [ValidationAssessmentController::class, 'update'])->whereNumber('assessment')->name('assessments.update');
    Route::delete('assessments/{assessment}', [ValidationAssessmentController::class, 'destroy'])->whereNumber('assessment')->name('assessments.destroy');

    Route::get('plans', [ValidationPlanController::class, 'index'])->name('plans.index');
    Route::post('plans', [ValidationPlanController::class, 'store'])->name('plans.store');
    Route::post('plans/{plan}/confirm', [ValidationPlanController::class, 'confirm'])->whereNumber('plan')->name('plans.confirm');
    Route::post('plans/{plan}/cancel', [ValidationPlanController::class, 'cancel'])->whereNumber('plan')->name('plans.cancel');

    // Tahap 3: QCO (PRNI) and IM (QA) number registers.
    Route::get('qco', [ValidationQcoController::class, 'index'])->name('qco.index');
    Route::post('qco', [ValidationQcoController::class, 'store'])->name('qco.store');
    Route::post('qco/{qco}/void', [ValidationQcoController::class, 'void'])->whereNumber('qco')->name('qco.void');

    Route::get('im', [ValidationImController::class, 'index'])->name('im.index');
    Route::post('im', [ValidationImController::class, 'store'])->name('im.store');
    Route::post('im/brands', [ValidationImController::class, 'storeBrand'])->name('im.brands.store');
    Route::post('im/{im}/revise', [ValidationImController::class, 'revise'])->whereNumber('im')->name('im.revise');
    Route::post('im/{im}/void', [ValidationImController::class, 'void'])->whereNumber('im')->name('im.void');
});
