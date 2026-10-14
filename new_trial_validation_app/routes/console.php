<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Self-learning (shadow mode). Needs `* * * * * php artisan schedule:run` in cron on the server.
Schedule::command('ai:quality-scan --apply')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('ai:learn')->dailyAt('02:00')->withoutOverlapping();
