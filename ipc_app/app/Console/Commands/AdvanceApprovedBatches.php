<?php

namespace App\Console\Commands;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use Illuminate\Console\Command;

/**
 * `php artisan ipc:advance-approved-batches [--dry-run]` — one-off cleanup (UAT Observasi O-1).
 *
 * Batches approved before the 2026-10-05 approval rule change can have every required stage
 * Approved yet still sit at current_stage = approval, because only SaveApproval ever advances a
 * batch to completed. They never show in the Approval Queue (nothing is pending).
 * Safe to re-run: it only touches batches still at `approval` with all required stages Approved.
 */
class AdvanceApprovedBatches extends Command
{
    protected $signature = 'ipc:advance-approved-batches {--dry-run : Tampilkan batch yang akan dimajukan tanpa menyimpan}';

    protected $description = 'Majukan batch yang tertahan di tahap Approval padahal semua bagian wajib sudah Approved ke Selesai';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $advanced = 0;

        IpcBatch::query()
            ->where('current_stage', IpcBatch::STAGE_APPROVAL)
            ->with('approvals')
            ->each(function (IpcBatch $batch) use ($dryRun, &$advanced) {
                // Both approval levels must be Approved (two-level approval, 2026-11-21).
                $approved = $batch->approvals
                    ->filter(fn (IpcApproval $approval) => $approval->isFullyApproved())
                    ->pluck('stage');

                if (! collect(IpcApproval::requiredStagesFor($batch))->every(fn ($s) => $approved->contains($s))) {
                    return;
                }

                $this->line(($dryRun ? '[dry-run] ' : '').'Batch #'.$batch->id.' '.$batch->no_batch.' -> completed');

                if (! $dryRun) {
                    // Keep updated_at: the dashboard's "Selesai hari ini" counts completed batches by
                    // updated_at, so touching it would report these old batches as finished today.
                    $batch->timestamps = false;
                    $batch->update(['current_stage' => IpcBatch::STAGE_COMPLETED]);
                }

                $advanced++;
            });

        $this->info($advanced.' batch '.($dryRun ? 'akan dimajukan.' : 'dimajukan ke Selesai.'));

        return self::SUCCESS;
    }
}
