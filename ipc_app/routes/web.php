<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('lookup/products', [LookupController::class, 'products'])->name('lookup.products');
    Route::get('lookup/batches', [LookupController::class, 'batches'])->name('lookup.batches');
});

require __DIR__.'/batches.php';
require __DIR__.'/vision.php';
require __DIR__.'/masters.php';
require __DIR__.'/users.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
