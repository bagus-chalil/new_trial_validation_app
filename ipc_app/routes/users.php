<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:manage-users'])->group(function () {
    Route::get('users', [UserRoleController::class, 'index'])->name('users.index');
    Route::post('users', [UserRoleController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [UserRoleController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/role', [UserRoleController::class, 'updateRole'])->name('users.update-role');
    Route::patch('users/{user}/status', [UserRoleController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('users.impersonate');
});

// Reached while logged in as the impersonated (usually non-admin) user, so it must NOT sit behind
// `can:manage-users` — the session's stored impersonator id is the authorisation.
Route::post('impersonate/leave', [ImpersonationController::class, 'destroy'])->middleware('auth')->name('impersonate.leave');
