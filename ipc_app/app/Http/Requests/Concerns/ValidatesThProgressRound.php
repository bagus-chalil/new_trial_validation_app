<?php

namespace App\Http\Requests\Concerns;

use App\Models\IpcBatch;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared TH Progress rules for Filling / Packing / Finished Check (user, 2026-10-01): QC runs
 * filling → packing → FG as one round, then repeats the loop next shift. So a stage only needs
 * the previous stage *saved* to be opened, but can only be finalized once the previous stage is
 * finalized, and each stage gets at most IpcBatch::MAX_TH_PROGRESS rounds — the last one must be
 * "Selesaikan", otherwise the batch would be stuck with no saves left and nothing finalized.
 */
trait ValidatesThProgressRound
{
    protected function validateThProgressRound(
        Validator $validator,
        int $currentSaveCount,
        bool $finalize,
        ?string $previousStageLabel = null,
        bool $previousStageCompleted = true,
    ): void {
        $max = IpcBatch::MAX_TH_PROGRESS;

        if ($currentSaveCount >= $max) {
            $validator->errors()->add('progress', "TH Progress sudah mencapai batas maksimal {$max}.");

            return;
        }

        if (! $finalize && $currentSaveCount + 1 >= $max) {
            $validator->errors()->add('progress', "TH Progress ke-{$max} adalah yang terakhir — gunakan Selesaikan.");

            return;
        }

        if ($finalize && $previousStageLabel !== null && ! $previousStageCompleted) {
            $validator->errors()->add('progress', "Selesaikan {$previousStageLabel} terlebih dahulu.");
        }
    }
}
