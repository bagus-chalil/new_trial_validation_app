<?php

namespace App\Console\Commands;

use App\Models\Trial;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('trials:auto-archive {--days= : Archive trials decided more than this many days ago (default config trials.auto_archive_days)} {--dry-run : Only report how many trials would be archived}')]
#[Description('Archives finished (Approved/Rejected) trials whose final decision is older than N days (default 1 year), hiding them from the normal lists. Reversible from Admin > Archive.')]
class AutoArchiveTrials extends Command
{
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('trials.auto_archive_days'));
        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);

        // Decision date, falling back to the last update for legacy rows
        // that never recorded approved_at/rejected_at.
        $query = Trial::query()
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereIn('progress_status', ['Approved', 'Rejected'])
                    ->orWhere('final_decision', 'Rejected');
            })
            ->whereRaw('COALESCE(approved_at, rejected_at, updated_at, created_at) < ?', [$cutoff]);

        if ($this->option('dry-run')) {
            $this->line("Would archive {$query->count()} trial(s) decided before {$cutoff->toDateString()}.");

            return self::SUCCESS;
        }

        $count = 0;
        $query->each(function (Trial $trial) use (&$count) {
            $trial->archived_at = Carbon::now();
            $trial->archived_by = null;
            $trial->save();
            $count++;
        });

        $this->line("Archived {$count} trial(s) decided before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
