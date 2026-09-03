<?php

namespace App\Actions\StartupInspections;

use App\Models\IpcBatch;
use App\Models\StartupInspection;
use App\Models\StartupInspectionItem;
use App\Models\StartupInspectionSample;
use App\Models\StartupInspectionTestResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveStartupInspection
{
    public function handle(IpcBatch $batch, User $user, array $data): StartupInspection
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $inspection = StartupInspection::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                ['user_id' => $user->id, 'completed_at' => now()],
            );

            foreach ($data['items'] ?? [] as $parameterKey => $item) {
                StartupInspectionItem::updateOrCreate(
                    ['startup_inspection_id' => $inspection->id, 'parameter_key' => $parameterKey],
                    ['status' => $item['status'], 'remark' => $item['remark'] ?? null],
                );
            }

            foreach ($data['samples'] ?? [] as $sample) {
                $volumeWeight = $sample['volume_weight'] ?? null;
                $weightMasterBox = $sample['weight_master_box'] ?? null;

                if (blank($volumeWeight) && blank($weightMasterBox)) {
                    continue;
                }

                StartupInspectionSample::updateOrCreate(
                    ['startup_inspection_id' => $inspection->id, 'sample_no' => $sample['sample_no']],
                    ['volume_weight' => $volumeWeight, 'weight_master_box' => $weightMasterBox],
                );
            }

            foreach ($data['test_results'] ?? [] as $masterTestTypeId => $result) {
                StartupInspectionTestResult::updateOrCreate(
                    ['startup_inspection_id' => $inspection->id, 'master_test_type_id' => $masterTestTypeId],
                    ['is_performed' => (bool) ($result['is_performed'] ?? false), 'remark' => $result['remark'] ?? null],
                );
            }

            return $inspection;
        });
    }
}
