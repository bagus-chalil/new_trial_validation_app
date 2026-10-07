<?php

namespace App\Actions\FillingChecks;

use App\Models\FillingCheck;
use App\Models\FillingCheckSample;
use App\Models\IpcBatch;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes Filling Check using the latest saved TH Progress round instead of a freshly filled
 * form (user, 2026-10-07): QC that saved its last real round as a draft (e.g. the process ended
 * at TH 2) can't run another trial just to fill the form again, so "Selesaikan" may adopt the
 * newest revision as the final record. No new revision/save_count — that round is simply marked
 * as the final one, and the live row (which a draft save blanks) is restored from it so the
 * approval/print reports read the same data they would after a normal Selesaikan.
 */
class CompleteFillingCheckFromHistory
{
    public function handle(IpcBatch $batch, FillingCheck $fillingCheck): FillingCheck
    {
        return DB::transaction(function () use ($batch, $fillingCheck) {
            $revision = $fillingCheck->revisions()->with('samples')->latest('revision_no')->firstOrFail();

            $fillingCheck->forceFill([
                'sample_bulk_odor_status' => $revision->sample_bulk_odor_status,
                'sample_leakage_test_status' => $revision->sample_leakage_test_status,
                'remarks' => $revision->remarks,
                'decision' => $revision->decision,
                'average_weight' => $revision->average_weight,
                'user_id' => $revision->user_id,
                'completed_at' => now(),
            ])->save();

            $fillingCheck->samples()->delete();

            $rows = $revision->samples
                ->map(fn ($sample) => [
                    'filling_check_id' => $fillingCheck->id,
                    'sample_no' => $sample->sample_no,
                    'weight_value' => $sample->weight_value,
                    'weight_result' => $sample->weight_result,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                FillingCheckSample::insert($rows);
            }

            $revision->update(['finalize' => true]);

            if ($batch->current_stage === IpcBatch::STAGE_FILLING) {
                $batch->update(['current_stage' => IpcBatch::STAGE_PACKING]);
            }

            return $fillingCheck->fresh(['samples', 'revisions.samples', 'revisions.user']);
        });
    }
}
