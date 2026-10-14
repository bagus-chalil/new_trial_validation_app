<?php

use App\Http\Controllers\BotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'throttle:20,1'])->group(function () {
    Route::post('bot/ask', [BotController::class, 'ask'])->name('bot.ask');
    Route::post('bot/{interaction}/rate', [BotController::class, 'rate'])->whereNumber('interaction')->name('bot.rate');
});
