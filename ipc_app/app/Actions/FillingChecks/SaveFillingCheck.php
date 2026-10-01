<?php

namespace App\Actions\FillingChecks;

use App\Models\FillingCheck;
use App\Models\FillingCheckRevision;
use App\Models\FillingCheckRevisionSample;
use App\Models\FillingCheckSample;
use App\Models\IpcBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveFillingCheck
{
    public function handle(IpcBatch $batch, User $user, array $data): FillingCheck
    {
        return DB::transaction(function () use ($batch, $user, $data) {
            $finalize = (bool) ($data['finalize'] ?? false);
            $fields = collect($data)->except(['samples', 'finalize'])->all();

            // weight_result is no longer computed — the (weight - empty bottle) / density formula
            // is done outside the system now (user, 2026-10-01). Average Weight is the plain mean
            // of the weights QC actually typed. The column stays for historical rows.
            $samples = collect($data['samples'] ?? [])
                ->filter(fn ($row) => filled($row['weight_value'] ?? null))
                ->map(fn ($row) => [
                    'sample_no' => $row['sample_no'],
                    'weight_value' => round((float) $row['weight_value'], 2),
                    'weight_result' => null,
                ]);

            // Recomputed on every save (draft or final) so QC sees it update while re-checking
            // samples over a shift, not only at the end.
            $averageWeight = $samples->isNotEmpty()
                ? round((float) $samples->avg('weight_value'), 2)
                : 0;

            $saveCount = ($batch->fillingCheck?->save_count ?? 0) + 1;

            $fillingCheck = FillingCheck::updateOrCreate(
                ['ipc_batch_id' => $batch->id],
                [
                    ...$fields,
                    'average_weight' => $averageWeight,
                    'user_id' => $user->id,
                    // TH_PROGESS: counts every Save/Save & End click (real legacy field), not
                    // just the final one — QC monitors the same batch repeatedly over a shift.
                    'save_count' => $saveCount,
                    'completed_at' => $finalize ? now() : null,
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

            // Immutable snapshot of this exact save — the `filling_checks` row above only ever
            // holds the *current* state, so without this, an earlier save's remarks/decision/
            // samples would be silently overwritten (or blanked) by the next save. Kept for
            // reporting on how QC's assessment evolved across TH_PROGESS revisions.
            $revision = FillingCheckRevision::create([
                'filling_check_id' => $fillingCheck->id,
                'revision_no' => $saveCount,
                'finalize' => $finalize,
                'sample_bulk_odor_status' => $fields['sample_bulk_odor_status'] ?? null,
                'sample_leakage_test_status' => $fields['sample_leakage_test_status'] ?? null,
                'remarks' => $fields['remarks'] ?? null,
                'decision' => $fields['decision'] ?? null,
                'average_weight' => $averageWeight,
                'user_id' => $user->id,
            ]);

            $revisionRows = $samples
                ->map(fn ($row) => [
                    'filling_check_revision_id' => $revision->id,
                    'sample_no' => $row['sample_no'],
                    'weight_value' => $row['weight_value'],
                    'weight_result' => $row['weight_result'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->values()
                ->all();

            if ($revisionRows !== []) {
                FillingCheckRevisionSample::insert($revisionRows);
            }

            if ($finalize && $batch->current_stage === IpcBatch::STAGE_FILLING) {
                $batch->update(['current_stage' => IpcBatch::STAGE_PACKING]);
            }

            return $fillingCheck->fresh(['samples', 'revisions.samples', 'revisions.user']);
        });
    }
}
