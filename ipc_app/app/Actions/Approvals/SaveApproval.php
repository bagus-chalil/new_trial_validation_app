<?php

namespace App\Actions\Approvals;

use App\Models\IpcApproval;
use App\Models\IpcApprovalRevision;
use App\Models\IpcBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveApproval
{
    public function handle(IpcBatch $batch, User $user, string $stage, array $data): IpcApproval
    {
        return DB::transaction(function () use ($batch, $user, $stage, $data) {
            $decidedAt = now();

            $approval = IpcApproval::updateOrCreate(
                ['ipc_batch_id' => $batch->id, 'stage' => $stage],
                [
                    'decision' => $data['decision'],
                    'approver_user_id' => $user->id,
                    'remarks' => $data['remarks'] ?? null,
                    'approved_at' => $decidedAt,
                ],
            );

            // Immutable snapshot of this decision — updateOrCreate above overwrites the live
            // ipc_approvals row in place, so without this a re-decided stage (e.g. Approved ->
            // Rejected) would lose all trace of the earlier decision/approver/remarks. Mirrors
            // FillingCheckRevision/PackingCheckRevision/FinishedCheckRevision's precedent.
            IpcApprovalRevision::create([
                'ipc_approval_id' => $approval->id,
                'revision_no' => ($approval->revisions()->max('revision_no') ?? 0) + 1,
                'decision' => $data['decision'],
                'approver_user_id' => $user->id,
                'remarks' => $data['remarks'] ?? null,
                'decided_at' => $decidedAt,
            ]);

            $approvedStages = IpcApproval::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('decision', IpcApproval::DECISION_APPROVED)
                ->pluck('stage');

            $allApproved = collect(IpcApproval::STAGES)->every(fn ($s) => $approvedStages->contains($s));

            if ($allApproved && $batch->current_stage === IpcBatch::STAGE_APPROVAL) {
                $batch->update(['current_stage' => IpcBatch::STAGE_PRINT]);
            }

            return $approval;
        });
    }
}
