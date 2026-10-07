<?php

namespace App\Actions\PackingChecks;

use App\Models\IpcBatch;
use App\Models\PackingCheck;
use App\Models\PackingCheckRevision;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes Packing Check using the latest saved TH Progress round — same intent as
 * CompleteFillingCheckFromHistory. The revision shares column names with packing_checks for
 * everything it snapshots, so restoring the live row is a straight fillable-keyed copy back;
 * the carried-forward fields a revision doesn't hold (line leader, coding machine, Data
 * Timbang) are already on the live row, since draft saves never blank them.
 */
class CompletePackingCheckFromHistory
{
    public function handle(IpcBatch $batch, PackingCheck $packingCheck): PackingCheck
    {
        return DB::transaction(function () use ($batch, $packingCheck) {
            $revision = $packingCheck->revisions()->latest('revision_no')->firstOrFail();

            $restored = collect($revision->getAttributes())
                ->only((new PackingCheckRevision)->getFillable())
                ->except(['packing_check_id', 'revision_no', 'finalize'])
                ->all();

            $packingCheck->forceFill([
                ...$restored,
                'completed_at' => now(),
            ])->save();

            $revision->update(['finalize' => true]);

            if ($batch->current_stage === IpcBatch::STAGE_PACKING) {
                $batch->update(['current_stage' => IpcBatch::STAGE_FINISHED]);
            }

            return $packingCheck->fresh(['revisions.user']);
        });
    }
}
