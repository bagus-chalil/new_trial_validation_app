<?php

namespace App\Actions\Approvals;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcReportDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Minta Revisi": the approver sends a report back to QC instead of approving/rejecting it.
 *
 * - Records a `Revision` decision (with the approver's note) on the approval row(s) of the
 *   decided report — all parts of a combined Fillpack + FG form, since that form is one document
 *   with one decision — through SaveApproval, so the decision keeps its immutable history row.
 * - Reopens only the check stages the approver picked (clears `completed_at`, which is exactly
 *   what unlocks each stage's edit page) and moves the batch back to the earliest one.
 * - Nothing is deleted: the saved rounds/revisions of each reopened stage stay as they were.
 *   When QC finalizes the last reopened stage, IpcBatch::advanceFrom() puts the batch back in
 *   Approval and clears the Revision decision.
 */
class RequestApprovalRevision
{
    public function __construct(private SaveApproval $saveApproval) {}

    /**
     * @param  list<string>  $reviseStages  check stages to reopen (startup/filling/packing/finished)
     */
    public function handle(IpcBatch $batch, User $user, string $reportStage, array $reviseStages, string $remarks): void
    {
        DB::transaction(function () use ($batch, $user, $reportStage, $reviseStages, $remarks) {
            $decidedParts = $reportStage === IpcReportDocument::KEY_FILLING_PACKING_FG
                ? IpcApproval::combinedStagesFor($batch)
                : [$reportStage];

            foreach ($decidedParts as $part) {
                $this->saveApproval->handle($batch, $user, $part, [
                    'decision' => IpcApproval::DECISION_REVISION,
                    'remarks' => $remarks,
                ]);
            }

            foreach ($reviseStages as $stage) {
                $this->reopen($batch, $stage);
            }

            $earliest = collect(IpcBatch::CHECK_STAGES)->first(fn (string $stage) => in_array($stage, $reviseStages, true));

            $batch->update(['current_stage' => $earliest]);
        });
    }

    private function reopen(IpcBatch $batch, string $stage): void
    {
        match ($stage) {
            IpcBatch::STAGE_STARTUP => $this->reopenStartup($batch),
            IpcBatch::STAGE_FILLING => $batch->fillingCheck()->update(['completed_at' => null]),
            IpcBatch::STAGE_PACKING => $batch->packingCheck()->update(['completed_at' => null]),
            IpcBatch::STAGE_FINISHED => $batch->finishedCheck()->update(['completed_at' => null]),
            default => null,
        };
    }

    /** Startup Check plus its Start Inspection sub-form, both locked by their own completed_at. */
    private function reopenStartup(IpcBatch $batch): void
    {
        $batch->startupCheck()->update(['completed_at' => null]);
        $batch->startupInspection()->update(['completed_at' => null]);
    }
}
