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
Schedule::command('ai:bot-prune')->dailyAt('03:00')->withoutOverlapping();

// Projects: catch up Validation stages whose linked trial was changed by the
// legacy app (it writes trials_header directly, bypassing the in-app sync).
// Idempotent, no notifications. Drop once the legacy app is switched off (Fase 4).
Schedule::command('projects:sync-trial-stages')->hourly()->withoutOverlapping();

// IM (QA): an approved future revision becomes Effective on its release date.
Schedule::command('validation:im-release')->hourly()->withoutOverlapping();

// Archive finished trials after a year (reversible from Admin > Archive).
Schedule::command('trials:auto-archive')->dailyAt('03:30')->withoutOverlapping();
