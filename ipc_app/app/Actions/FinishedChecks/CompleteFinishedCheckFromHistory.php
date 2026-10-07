<?php

namespace App\Actions\FinishedChecks;

use App\Models\FinishedCheck;
use App\Models\FinishedCheckRevision;
use App\Models\FinishedCheckSample;
use App\Models\IpcBatch;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes Finished Check using the latest saved TH Progress round — same intent as
 * CompleteFillingCheckFromHistory. Finished Check drafts don't blank the live row, so it
 * usually already matches the latest revision; restoring from the revision anyway keeps the
 * final record exactly equal to the round it's marked as.
 */
class CompleteFinishedCheckFromHistory
{
    public function handle(IpcBatch $batch, FinishedCheck $finishedCheck): FinishedCheck
    {
        return DB::transaction(function () use ($batch, $finishedCheck) {
            $revision = $finishedCheck->revisions()->with('samples')->latest('revision_no')->firstOrFail();

            $restored = collect($revision->getAttributes())
                ->only((new FinishedCheckRevision)->getFillable())
                ->except(['finished_check_id', 'revision_no', 'finalize'])
                ->all();

            $finishedCheck->forceFill([
                ...$restored,
                'completed_at' => now(),
            ])->save();

            $revisionSamples = $revision->samples->keyBy('parameter_key');

            foreach (FinishedCheckSample::PARAMETER_KEYS as $key) {
                $row = $revisionSamples->get($key);

                FinishedCheckSample::updateOrCreate(
                    ['finished_check_id' => $finishedCheck->id, 'parameter_key' => $key],
                    [
                        'ac' => $row?->ac,
                        'cd' => $row?->cd,
                        'md' => $row?->md,
                        'mnd' => $row?->mnd,
                        'remark' => $row?->remark,
                    ],
                );
            }

            $revision->update(['finalize' => true]);

            if ($batch->current_stage === IpcBatch::STAGE_FINISHED) {
                $batch->update(['current_stage' => IpcBatch::STAGE_APPROVAL]);
            }

            return $finishedCheck->fresh(['samples', 'revisions.samples', 'revisions.user']);
        });
    }
}
