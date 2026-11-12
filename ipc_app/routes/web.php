<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LookupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Polled by the front end (VersionWatcher) so a tablet that keeps a tab open for
// days learns about a new deploy without having to navigate first.
Route::get('app-version', fn () => response()
    ->json(['version' => Inertia::getVersion()])
    ->header('Cache-Control', 'no-store, max-age=0'))
    ->name('app-version');

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
