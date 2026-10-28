<?php

namespace App\Actions\MicroChecks;

use App\Models\IpcBatch;
use App\Models\MicroCheck;
use App\Models\MicroCheckRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveMicroCheck
{
    /**
     * @param  array<string, string|null>  $data  the six result/std values (all six on finalize)
     * @param  bool  $finalize  "Selesaikan": locks the record and sends the batch on to Approval
     */
    public function handle(IpcBatch $batch, User $user, array $data, bool $finalize = false): MicroCheck
    {
        return DB::transaction(function () use ($batch, $user, $data, $finalize) {
            $values = collect(MicroCheck::valueColumns())->mapWithKeys(fn (string $column) => [$column => $data[$column] ?? null])->all();
            $existing = $batch->microCheck;
            $saveCount = ($existing?->save_count ?? 0) + 1;

            // The row always holds the latest values; who/when/what of every save is kept in
            // micro_check_revisions, so a correction never erases the earlier entry.
            $microCheck = MicroCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [...$values, 'user_id' => $user->id, 'save_count' => $saveCount, 'completed_at' => $finalize ? now() : null],
            );

            MicroCheckRevision::create([
                ...$values,
                'micro_check_id' => $microCheck->id,
                'revision_no' => $saveCount,
                'finalize' => $finalize,
                'user_id' => $user->id,
            ]);

            // Finished Good's own Selesaikan no longer moves the batch on — this is the hand-off
            // to Approval (the Finished stage is only done once Micro Check is too).
            if ($finalize && $batch->current_stage === IpcBatch::STAGE_FINISHED) {
                $batch->update(['current_stage' => $batch->nextStageAfter(IpcBatch::STAGE_FINISHED)]);
            }

            return $microCheck;
        });
    }
}
