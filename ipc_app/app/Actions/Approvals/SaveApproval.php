<?php

namespace App\Actions\Approvals;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveApproval
{
    public function handle(IpcBatch $batch, User $user, string $stage, array $data): IpcApproval
    {
        return DB::transaction(function () use ($batch, $user, $stage, $data) {
            $approval = IpcApproval::updateOrCreate(
                ['ipc_batch_id' => $batch->id, 'stage' => $stage],
                [
                    'decision' => $data['decision'],
                    'approver_user_id' => $user->id,
                    'remarks' => $data['remarks'] ?? null,
                    'approved_at' => now(),
                ],
            );

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
