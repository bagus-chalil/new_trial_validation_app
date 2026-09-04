<?php

namespace App\Actions\Prints;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcPrintLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records one print event per PDF view (real legacy print is a bare Print() flag-flip with no
 * history at all — this app's own enhancement, same class of addition as IpcApproval's
 * decision/remarks/approver — confirmed with the user 2026-09-04: opening the print preview IS
 * the print action at this final stage, logged every time, no separate confirm step). Once every
 * stage has at least one log, the batch auto-advances 'print' -> 'completed', mirroring
 * SaveApproval's all-three-Approved-advances-to-print pattern.
 */
class LogPrint
{
    public function handle(IpcBatch $batch, User $user, string $stage): IpcPrintLog
    {
        return DB::transaction(function () use ($batch, $user, $stage) {
            $log = IpcPrintLog::create([
                'ipc_batch_id' => $batch->id,
                'stage' => $stage,
                'printed_by_user_id' => $user->id,
                'printed_at' => now(),
            ]);

            $printedStages = IpcPrintLog::query()
                ->where('ipc_batch_id', $batch->id)
                ->distinct()
                ->pluck('stage');

            $allPrinted = collect(IpcApproval::STAGES)->every(fn ($s) => $printedStages->contains($s));

            if ($allPrinted && $batch->current_stage === IpcBatch::STAGE_PRINT) {
                $batch->update(['current_stage' => IpcBatch::STAGE_COMPLETED]);
            }

            return $log;
        });
    }
}
