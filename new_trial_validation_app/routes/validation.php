<?php

use App\Http\Controllers\ValidationJourneyController;
use Illuminate\Support\Facades\Route;

// Trial Validation v2.0: Product Journey per Case.
Route::middleware(['auth', 'verified'])->prefix('journey')->as('journey.')->group(function () {
    Route::get('/', [ValidationJourneyController::class, 'index'])->name('index');
    Route::get('{case}', [ValidationJourneyController::class, 'show'])->whereNumber('case')->name('show');
    Route::post('{case}/trials/{trial}/client-approval', [ValidationJourneyController::class, 'clientApproval'])
        ->whereNumber('case')->whereNumber('trial')->name('client-approval');
});
