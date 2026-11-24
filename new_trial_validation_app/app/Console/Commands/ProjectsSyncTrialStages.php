<?php

namespace App\Console\Commands;

use App\Actions\Projects\SyncProjectStagesFromTrial;
use App\Models\ProjectStage;
use App\Models\Trial;
use Illuminate\Console\Command;

/**
 * Projects module Fase 4: re-syncs every trial-linked stage from its trial.
 * The live sync runs inside this app's trial actions; this command catches
 * up on status changes made elsewhere (the legacy PHP app writes the same
 * trials_header table) and is safe to run any time (idempotent). Writes only
 * pm_stages / pm_projects / activity_logs, never trials_header.
 */
class ProjectsSyncTrialStages extends Command
{
    protected $signature = 'projects:sync-trial-stages {--dry-run : List what would change, write nothing}';

    protected $description = 'Sync Validation stages linked to a Trial from the trial status';

    public function handle(SyncProjectStagesFromTrial $sync): int
    {
        $trialIds = ProjectStage::query()->whereNotNull('trial_id')->distinct()->pluck('trial_id');
        $trials = Trial::query()->whereIn('id', $trialIds)->get();

        $this->info("Linked trials: {$trials->count()}");

        if ($this->option('dry-run')) {
            foreach ($trials as $trial) {
                $this->line("{$trial->trial_code}: {$trial->progress_status}");
            }

            return self::SUCCESS;
        }

        $changed = 0;
        foreach ($trials as $trial) {
            // No PIC notifications for a catch-up run.
            $changed += $sync($trial, null, notify: false);
        }

        $this->info("Stages updated: {$changed}");

        return self::SUCCESS;
    }
}
