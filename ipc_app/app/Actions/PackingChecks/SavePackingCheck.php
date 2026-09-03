<?php

namespace App\Actions\PackingChecks;

use App\Models\IpcBatch;
use App\Models\PackingCheck;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SavePackingCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): PackingCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $packingCheck = PackingCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$data,
                    'user_id' => $user->id,
                    'completed_at' => now(),
                ],
            );

            if ($batch->current_stage === IpcBatch::STAGE_PACKING) {
                $batch->update(['current_stage' => IpcBatch::STAGE_FINISHED]);
            }

            return $packingCheck;
        });
    }
}
