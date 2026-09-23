<?php

namespace App\Actions\StartupChecks;

use App\Models\IpcBatch;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveStartupCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): StartupCheck
    {
        // Mixing Date and Line are chosen here (not at batch creation, see IpcBatchController)
        // but they're batch-level fields, not startup_checks columns — route them onto the batch.
        $mixingDate = Arr::pull($data, 'mixing_date');
        $masterLineId = Arr::pull($data, 'master_line_id');

        return DB::transaction(function () use ($batch, $user, $data, $mixingDate, $masterLineId) {
            $startupCheck = StartupCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$data,
                    'user_id' => $user->id,
                    'completed_at' => now(),
                ],
            );

            $batch->update([
                'mixing_date' => $mixingDate,
                'master_line_id' => $masterLineId,
                ...($batch->current_stage === IpcBatch::STAGE_STARTUP ? ['current_stage' => IpcBatch::STAGE_FILLING] : []),
            ]);

            return $startupCheck;
        });
    }
}
