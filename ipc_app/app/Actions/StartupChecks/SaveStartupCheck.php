<?php

namespace App\Actions\StartupChecks;

use App\Models\IpcBatch;
use App\Models\StartupBottleWeight;
use App\Models\StartupCheck;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveStartupCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): StartupCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $fields = collect($data)->except('bottle_weights')->all();

            $weights = collect($data['bottle_weights'] ?? [])
                ->filter(fn ($row) => filled($row['weight_value'] ?? null));

            // Legacy computes this as the mean of the 30 bottle-weight samples at BottleData
            // save time (Controls/832.json's Label3 formula) and carries it via a session
            // variable into Start_Check's own save — recomputed fresh here instead, since
            // both saves happen in this one request.
            $averageOfEmptyBottleWeight = $weights->isNotEmpty()
                ? round((float) $weights->avg('weight_value'), 4)
                : null;

            $startupCheck = StartupCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$fields,
                    'average_of_empty_bottle_weight' => $averageOfEmptyBottleWeight,
                    'user_id' => $user->id,
                    'completed_at' => now(),
                ],
            );

            $startupCheck->bottleWeights()->delete();

            $rows = $weights
                ->map(fn ($row) => [
                    'startup_check_id' => $startupCheck->id,
                    'sample_no' => $row['sample_no'],
                    'weight_value' => $row['weight_value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                StartupBottleWeight::insert($rows);
            }

            if ($batch->current_stage === IpcBatch::STAGE_STARTUP) {
                $batch->update(['current_stage' => IpcBatch::STAGE_FILLING]);
            }

            return $startupCheck->fresh('bottleWeights');
        });
    }
}
