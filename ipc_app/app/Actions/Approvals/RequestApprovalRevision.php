<?php

namespace App\Actions\Approvals;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcReportDocument;
use App\Models\IpcRoundCorrection;
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
 * - The approver can also point at the exact TH Progress rounds that are wrong (`$reviseRounds`,
 *   stage => round numbers). Each becomes an open `ipc_round_corrections` row: QC corrects that
 *   round in place (see CorrectCheckRound) with a full before/after audit, instead of only being
 *   able to append yet another round. Startup has no rounds, so reopening it stores its "before"
 *   snapshot there and SaveStartupCheck adds the "after" once it is finalized again.
 * - Nothing is deleted: the saved rounds/revisions of each reopened stage stay as they were.
 *   When QC finalizes the last reopened stage, IpcBatch::advanceFrom() puts the batch back in
 *   Approval and clears the Revision decision.
 */
class RequestApprovalRevision
{
    public function __construct(private SaveApproval $saveApproval) {}

    /**
     * @param  list<string>  $reviseStages  check stages to reopen (startup/filling/packing/finished)
     * @param  array<string, list<int>>  $reviseRounds  stage => TH Progress round numbers to correct
     */
    public function handle(IpcBatch $batch, User $user, string $reportStage, array $reviseStages, string $remarks, array $reviseRounds = []): void
    {
        DB::transaction(function () use ($batch, $user, $reportStage, $reviseStages, $remarks, $reviseRounds) {
            $decidedParts = $reportStage === IpcReportDocument::KEY_FILLING_PACKING_FG
                ? IpcApproval::combinedStagesFor($batch)
                : [$reportStage];

            foreach ($decidedParts as $part) {
                $this->saveApproval->handle($batch, $user, $part, [
                    'decision' => IpcApproval::DECISION_REVISION,
                    'remarks' => $remarks,
                ]);
            }

            // Audit rows first: the "before" snapshot of Startup must be taken before it is reopened.
            foreach ($reviseStages as $stage) {
                $this->recordRequests($batch, $user, $stage, $reviseRounds[$stage] ?? [], $remarks);
            }

            foreach ($reviseStages as $stage) {
                $this->reopen($batch, $stage);
            }

            $earliest = collect(IpcBatch::CHECK_STAGES)->first(fn (string $stage) => in_array($stage, $reviseStages, true));

            $batch->update(['current_stage' => $earliest]);
        });
    }

    /** @param  list<int>  $rounds */
    private function recordRequests(IpcBatch $batch, User $user, string $stage, array $rounds, string $remarks): void
    {
        $base = [
            'ipc_batch_id' => $batch->id,
            'stage' => $stage,
            'status' => IpcRoundCorrection::STATUS_OPEN,
            'requested_by' => $user->id,
            'request_note' => $remarks,
            'requested_at' => now(),
        ];

        if ($stage === IpcBatch::STAGE_STARTUP) {
            IpcRoundCorrection::create([...$base, 'revision_no' => null, 'before_data' => $this->startupSnapshot($batch)]);

            return;
        }

        foreach (array_unique(array_map('intval', $rounds)) as $no) {
            // A round already waiting for correction is not requested twice.
            IpcRoundCorrection::firstOrCreate(
                ['ipc_batch_id' => $batch->id, 'stage' => $stage, 'revision_no' => $no, 'status' => IpcRoundCorrection::STATUS_OPEN],
                $base,
            );
        }
    }

    /** @return array<string, mixed> */
    public static function startupSnapshot(IpcBatch $batch): array
    {
        $check = $batch->startupCheck()->first();
        $inspection = $batch->startupInspection()->with(['items', 'samples', 'testResults'])->first();

        return [
            'startup_check' => $check?->getAttributes(),
            'batch' => $batch->only(['mixing_date', 'exp_date', 'shelf_life_months', 'primary_packaging_type', 'master_line_id']),
            'inspection' => $inspection?->getAttributes(),
            'inspection_items' => $inspection?->items->map->getAttributes()->all() ?? [],
            'inspection_samples' => $inspection?->samples->map->getAttributes()->all() ?? [],
            'inspection_tests' => $inspection?->testResults->map->getAttributes()->all() ?? [],
        ];
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
