<?php

namespace App\Actions\Mixing;

use App\Models\ActivityLog;
use App\Models\Mixing;
use App\Models\MixingResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Save the parameter results of a Mixing (same normalisation as the Trial
 * validation: N/A forces "N/A", OK with an empty result becomes "Conform").
 */
class SaveMixingValidation
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __invoke(Mixing $mixing, array $rows, User $user): void
    {
        DB::transaction(function () use ($mixing, $rows, $user) {
            foreach ($rows as $row) {
                $decision = (string) $row['decision'];
                $result = trim((string) ($row['result'] ?? ''));
                if ($decision === 'N/A') {
                    $result = 'N/A';
                } elseif ($decision === 'OK' && $result === '') {
                    $result = 'Conform';
                }

                MixingResult::query()->updateOrCreate(
                    ['mixing_id' => $mixing->id, 'parameter_id' => (int) $row['parameter_id']],
                    ['decision' => $decision, 'result_value' => $result, 'remark' => trim((string) ($row['remark'] ?? '')) ?: null],
                );
            }

            if ($mixing->progress_status === 'Draft') {
                $mixing->current_step = 'Attachment';
                $mixing->save();
            }

            ActivityLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'action' => 'UPDATE',
                'module' => 'MIXING_VALIDATION',
                'record_id' => (string) $mixing->id,
                'record_label' => $mixing->mixing_code,
                'old_data' => null,
                'new_data' => json_encode(['rows' => count($rows)]),
            ]);
        });
    }
}
