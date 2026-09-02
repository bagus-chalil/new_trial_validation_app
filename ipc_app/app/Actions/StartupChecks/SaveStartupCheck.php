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

            $startupCheck = StartupCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [...$fields, 'user_id' => $user->id, 'completed_at' => now()],
            );

            $startupCheck->bottleWeights()->delete();

            $rows = collect($data['bottle_weights'] ?? [])
                ->filter(fn ($row) => filled($row['weight_value'] ?? null))
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
