<?php

namespace App\Actions\FillingChecks;

use App\Models\FillingCheck;
use App\Models\FillingCheckSample;
use App\Models\IpcBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveFillingCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): FillingCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $fields = collect($data)->except('samples')->all();

            // Real per-sample formula from the export (Controls/625.json):
            // WEIGHT_SAMPLE_N_RESULT = (WEIGHT_SAMPLE_N - Start.AVERAGE_OF_EMPTY_BOTTLE_WEIGHT) / Start.DENSITY.
            // Requires the batch's Startup Check to already be completed (guaranteed by the
            // controller, since a batch only reaches the filling stage once that's done).
            $averageOfEmptyBottleWeight = (float) $batch->startupCheck->average_of_empty_bottle_weight;
            $density = (float) $batch->startupCheck->density;

            $samples = collect($data['samples'] ?? [])
                ->filter(fn ($row) => filled($row['weight_value'] ?? null))
                ->map(function ($row) use ($averageOfEmptyBottleWeight, $density) {
                    $weightValue = (float) $row['weight_value'];

                    return [
                        'sample_no' => $row['sample_no'],
                        'weight_value' => $weightValue,
                        'weight_result' => $density !== 0.0
                            ? round(($weightValue - $averageOfEmptyBottleWeight) / $density, 4)
                            : null,
                    ];
                });

            // Legacy's own Label3_1 formula averages the 10 per-sample RESULT values, not the
            // raw weights (Controls/625.json).
            $averageWeight = $samples->isNotEmpty()
                ? round((float) $samples->avg('weight_result'), 4)
                : null;

            $fillingCheck = FillingCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$fields,
                    'average_weight' => $averageWeight,
                    'user_id' => $user->id,
                    'completed_at' => now(),
                ],
            );

            $fillingCheck->samples()->delete();

            $rows = $samples
                ->map(fn ($row) => [
                    'filling_check_id' => $fillingCheck->id,
                    'sample_no' => $row['sample_no'],
                    'weight_value' => $row['weight_value'],
                    'weight_result' => $row['weight_result'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                FillingCheckSample::insert($rows);
            }

            if ($batch->current_stage === IpcBatch::STAGE_FILLING) {
                $batch->update(['current_stage' => IpcBatch::STAGE_PACKING]);
            }

            return $fillingCheck->fresh('samples');
        });
    }
}
