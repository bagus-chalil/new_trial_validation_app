<?php

namespace App\Actions\FinishedChecks;

use App\Models\FinishedCheck;
use App\Models\FinishedCheckRevision;
use App\Models\FinishedCheckRevisionSample;
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
            $saveCount = ($batch->finishedCheck?->save_count ?? 0) + 1;

            $fields = [
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
            ];

            $finishedCheck = FinishedCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$fields,
                    'user_id' => $user->id,
                    // TH_PROGESS-style counter, same shape as filling_checks/packing_checks
                    // save_count: counts every Save/Save & End click, draft or final.
                    'save_count' => $saveCount,
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

            // Immutable snapshot of this exact save — finished_checks itself only ever holds the
            // *current* state, so without this an earlier save's quantities/decision/remarks/
            // samples would be silently overwritten by the next one. Mirrors
            // filling_check_revisions/packing_check_revisions, added after the user pointed out
            // Finished Check was the only save-capable stage with no "Riwayat Simpan" history.
            $revision = FinishedCheckRevision::create([
                ...$fields,
                'finished_check_id' => $finishedCheck->id,
                'revision_no' => $saveCount,
                'finalize' => $finalize,
                'user_id' => $user->id,
            ]);

            $revisionRows = [];
            foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
                $row = $samples[$key] ?? [];

                $revisionRows[] = [
                    'finished_check_revision_id' => $revision->id,
                    'parameter_key' => $key,
                    'ac' => $row['ac'] ?? null,
                    'cd' => $row['cd'] ?? null,
                    'md' => $row['md'] ?? null,
                    'mnd' => $row['mnd'] ?? null,
                    'remark' => $row['remark'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            FinishedCheckRevisionSample::insert($revisionRows);

            if ($finalize && $batch->current_stage === IpcBatch::STAGE_FINISHED) {
                $batch->update(['current_stage' => IpcBatch::STAGE_APPROVAL]);
            }

            return $finishedCheck->fresh(['samples', 'revisions.samples', 'revisions.user']);
        });
    }
}
