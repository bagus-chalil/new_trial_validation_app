<?php

use App\Http\Controllers\Internal\TrialStatusController;
use App\Http\Middleware\VerifyInternalApiToken;
use Illuminate\Support\Facades\Route;

// Server-to-server endpoints for sibling apps on the same host (not the future mobile /api/v1).
Route::prefix('internal')->middleware(VerifyInternalApiToken::class)->group(function () {
    Route::get('trial-status', [TrialStatusController::class, 'index'])->name('internal.trial-status');
});
