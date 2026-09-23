<?php

use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:manage-users'])->group(function () {
    Route::get('users', [UserRoleController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [UserRoleController::class, 'update'])->name('users.update-role');
});
