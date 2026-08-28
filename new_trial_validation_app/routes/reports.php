<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('reports')->as('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('approved', [ReportController::class, 'approved'])->name('approved');
    Route::get('approved/pdf', [ReportController::class, 'approvedPdf'])->name('approved.pdf');
    Route::get('rejected', [ReportController::class, 'rejected'])->name('rejected');
    Route::get('rejected/pdf', [ReportController::class, 'rejectedPdf'])->name('rejected.pdf');
    Route::get('trial-summary', [ReportController::class, 'trialSummary'])->name('trial-summary');
    Route::get('trial-summary/pdf', [ReportController::class, 'trialSummaryPdf'])->name('trial-summary.pdf');
    Route::get('department-review', [ReportController::class, 'departmentReview'])->name('department-review');
    Route::get('department-review/pdf', [ReportController::class, 'departmentReviewPdf'])->name('department-review.pdf');
    Route::get('audit-print-log', [ReportController::class, 'auditPrintLog'])->name('audit-print-log');
    Route::get('audit-print-log/pdf', [ReportController::class, 'auditPrintLogPdf'])->name('audit-print-log.pdf');
});
