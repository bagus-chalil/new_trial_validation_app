<?php

namespace App\Actions\Approvals;

use App\Models\IpcApproval;
use App\Models\IpcApprovalRevision;
use App\Models\IpcBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records one approval decision on a stage. Two-level approval (2026-11-21):
 *
 * - Level 1 (QC Verifikator) writes the original columns. Deciding Level 1 again — whatever the
 *   decision — clears any Level 2 decision on that stage, because Level 2 signed a version of the
 *   report that Level 1 has now re-judged. The cleared Level 2 decision stays in the history.
 * - Level 2 (Team Leader / Part Leader / Supervisor) writes the l2_* columns. It can only decide
 *   once Level 1 is Approved, and never the same person who gave Level 1 on that stage.
 *
 * A batch moves to Completed only once every required stage is Approved by BOTH levels.
 */
class SaveApproval
{
    public function handle(IpcBatch $batch, User $user, string $stage, array $data, int $level = IpcApproval::LEVEL_1): IpcApproval
    {
        return DB::transaction(function () use ($batch, $user, $stage, $data, $level) {
            $decidedAt = now();

            if ($level === IpcApproval::LEVEL_2) {
                $approval = $this->saveLevel2($batch, $user, $stage, $data, $decidedAt);
            } else {
                $approval = IpcApproval::updateOrCreate(
                    ['ipc_batch_id' => $batch->id, 'stage' => $stage],
                    [
                        'decision' => $data['decision'],
                        'approver_user_id' => $user->id,
                        'remarks' => $data['remarks'] ?? null,
                        'approved_at' => $decidedAt,
                        'l2_decision' => null,
                        'l2_approver_user_id' => null,
                        'l2_remarks' => null,
                        'l2_decided_at' => null,
                    ],
                );
            }

            // Immutable snapshot of this decision — updateOrCreate above overwrites the live
            // ipc_approvals row in place, so without this a re-decided stage (e.g. Approved ->
            // Rejected) would lose all trace of the earlier decision/approver/remarks. Mirrors
            // FillingCheckRevision/PackingCheckRevision/FinishedCheckRevision's precedent.
            IpcApprovalRevision::create([
                'ipc_approval_id' => $approval->id,
                'revision_no' => ($approval->revisions()->max('revision_no') ?? 0) + 1,
                'level' => $level,
                'decision' => $data['decision'],
                'approver_user_id' => $user->id,
                'remarks' => $data['remarks'] ?? null,
                'decided_at' => $decidedAt,
            ]);

            $approvedStages = IpcApproval::query()
                ->where('ipc_batch_id', $batch->id)
                ->fullyApproved()
                ->pluck('stage');

            $allApproved = collect(IpcApproval::requiredStagesFor($batch))->every(fn ($s) => $approvedStages->contains($s));

            if ($allApproved && $batch->current_stage === IpcBatch::STAGE_APPROVAL) {
                // Print is no longer a stage: every required approval Approved = batch finished.
                $batch->update(['current_stage' => IpcBatch::STAGE_COMPLETED]);
            }

            return $approval;
        });
    }

    private function saveLevel2(IpcBatch $batch, User $user, string $stage, array $data, $decidedAt): IpcApproval
    {
        $approval = IpcApproval::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', $stage)
            ->first();

        if (! $approval || $approval->decision !== IpcApproval::DECISION_APPROVED) {
            throw ValidationException::withMessages([
                'decision' => 'Approval Level 2 baru bisa diputuskan setelah Level 1 (QC Verifikator) menyetujui bagian ini.',
            ]);
        }

        if ((int) $approval->approver_user_id === $user->id) {
            throw ValidationException::withMessages([
                'decision' => 'Anda sudah memberi approval Level 1 pada bagian ini. Level 2 harus diputuskan oleh orang yang berbeda.',
            ]);
        }

        $approval->update([
            'l2_decision' => $data['decision'],
            'l2_approver_user_id' => $user->id,
            'l2_remarks' => $data['remarks'] ?? null,
            'l2_decided_at' => $decidedAt,
        ]);

        return $approval;
    }
}
