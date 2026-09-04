<?php

namespace App\Actions\FinishedChecks;

use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveFinishedCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): FinishedCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $finalize = (bool) ($data['finalize'] ?? false);

            $finishedCheck = FinishedCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    'quantity_wi' => $data['quantity_wi'] ?? null,
                    'masterbox' => $data['masterbox'] ?? null,
                    'no_pallet_qty' => $data['no_pallet_qty'] ?? null,
                    'quantity_sampling_aql' => $data['quantity_sampling_aql'] ?? null,
                    'quantity_sample_aql_cd' => $data['quantity_sample_aql_cd'] ?? null,
                    'quantity_sample_aql_md' => $data['quantity_sample_aql_md'] ?? null,
                    'quantity_sample_aql_mnd' => $data['quantity_sample_aql_mnd'] ?? null,
                    'quantity_special_inspection' => $data['quantity_special_inspection'] ?? null,
                    'quantity_special_inspection_cd' => $data['quantity_special_inspection_cd'] ?? null,
                    'quantity_special_inspection_md' => $data['quantity_special_inspection_md'] ?? null,
                    'quantity_special_inspection_mnd' => $data['quantity_special_inspection_mnd'] ?? null,
                    'line_leader_name' => $data['line_leader_name'] ?? null,
                    'disposition' => $data['disposition'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'user_id' => $user->id,
                    'completed_at' => $finalize ? now() : null,
                ],
            );

            $samples = $data['samples'] ?? [];

            foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
                $row = $samples[$key] ?? [];

                FinishedCheckSample::updateOrCreate(
                    ['finished_check_id' => $finishedCheck->id, 'parameter_key' => $key],
                    [
                        'ac' => $row['ac'] ?? null,
                        'cd' => $row['cd'] ?? null,
                        'md' => $row['md'] ?? null,
                        'mnd' => $row['mnd'] ?? null,
                        'remark' => $row['remark'] ?? null,
                    ],
                );
            }

            if ($finalize && $batch->current_stage === IpcBatch::STAGE_FINISHED) {
                $batch->update(['current_stage' => IpcBatch::STAGE_APPROVAL]);
            }

            return $finishedCheck;
        });
    }
}
