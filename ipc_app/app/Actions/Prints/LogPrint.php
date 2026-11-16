<?php

namespace App\Actions\Prints;

use App\Models\IpcBatch;
use App\Models\IpcPrintLog;
use App\Models\User;

/**
 * Records one print event per PDF view (real legacy print is a bare Print() flag-flip with no
 * history at all — this app's own enhancement, same class of addition as IpcApproval's
 * decision/remarks/approver). Opening the print preview IS the print action, logged every time.
 *
 * Since 2026-11-16 printing no longer drives the batch's stage: Print was retired as a workflow
 * stage and a batch is Completed as soon as every required approval is Approved (SaveApproval),
 * so this only keeps the "who printed what, when" history.
 */
class LogPrint
{
    public function handle(IpcBatch $batch, User $user, string $stage): IpcPrintLog
    {
        return IpcPrintLog::create([
            'ipc_batch_id' => $batch->id,
            'stage' => $stage,
            'printed_by_user_id' => $user->id,
            'printed_at' => now(),
        ]);
    }
}
