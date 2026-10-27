<?php

namespace App\Actions\MicroChecks;

use App\Models\IpcBatch;
use App\Models\MicroCheck;
use App\Models\MicroCheckRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveMicroCheck
{
    /** @param  array<string, string>  $data  the six result/std values */
    public function handle(IpcBatch $batch, User $user, array $data): MicroCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $values = collect($data)->only(MicroCheck::valueColumns())->all();
            $existing = $batch->microCheck;
            $saveCount = ($existing?->save_count ?? 0) + 1;

            // The row always holds the latest values; who/when/what of every save is kept in
            // micro_check_revisions, so a correction never erases the earlier entry.
            $microCheck = MicroCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [...$values, 'user_id' => $user->id, 'save_count' => $saveCount],
            );

            MicroCheckRevision::create([
                ...$values,
                'micro_check_id' => $microCheck->id,
                'revision_no' => $saveCount,
                'user_id' => $user->id,
            ]);

            return $microCheck;
        });
    }
}
