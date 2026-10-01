<?php

namespace App\Actions\StartupInspections;

use App\Models\StartupInspection;
use App\Models\StartupInspectionSample;
use Illuminate\Support\Facades\DB;

/**
 * Late fill of Start Inspection's Weight Master Box samples, reached from Packing Check.
 * Only weight_master_box is written — the rest of Start Inspection (checklist, Volume/Weight,
 * test results) was locked when it was completed at the start of the batch.
 */
class SaveStartupInspectionMasterBox
{
    public function handle(StartupInspection $inspection, array $samples): void
    {
        DB::transaction(function () use ($inspection, $samples) {
            foreach ($samples as $sample) {
                $weightMasterBox = $sample['weight_master_box'] ?? null;

                if (blank($weightMasterBox)) {
                    continue;
                }

                StartupInspectionSample::updateOrCreate(
                    ['startup_inspection_id' => $inspection->id, 'sample_no' => $sample['sample_no']],
                    ['weight_master_box' => $weightMasterBox],
                );
            }
        });
    }
}
