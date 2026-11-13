<?php

namespace App\Services\LineStatus;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use Illuminate\Support\Collection;

/**
 * What is running on a production line right now: the batches assigned to it that are not yet
 * completed/archived, with the stage they are at and the person (PIC) who last worked on that
 * stage. The PIC is derived from the current stage's check record (user_id), falling back to the
 * batch creator — it is an inference from activity, not an explicit check-in.
 */
class LineStatusBoard
{
    public const STAGE_LABELS = [
        IpcBatch::STAGE_STARTUP => 'Startup Check',
        IpcBatch::STAGE_FILLING => 'Filling Check',
        IpcBatch::STAGE_PACKING => 'Packing Check',
        IpcBatch::STAGE_FINISHED => 'Finished Good',
        IpcBatch::STAGE_APPROVAL => 'Menunggu Approval',
        IpcBatch::STAGE_PRINT => 'Siap Print',
    ];

    /**
     * @param  Collection<int, MasterLine>  $lines
     * @return array<int, list<array<string, mixed>>> active batches keyed by master_line_id
     */
    public function forLines(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $batches = IpcBatch::query()
            ->whereIn('master_line_id', $lines->pluck('id')->all())
            ->whereNull('archived_at')
            ->where('current_stage', '!=', IpcBatch::STAGE_COMPLETED)
            ->with(['masterProduct', 'creator', 'startupCheck.user', 'fillingCheck.user', 'packingCheck.user', 'finishedCheck.user'])
            ->orderByDesc('updated_at')
            ->get();

        $grouped = [];
        foreach ($batches as $batch) {
            $grouped[$batch->master_line_id][] = $this->row($batch);
        }

        return $grouped;
    }

    /** @return array<string, mixed> */
    private function row(IpcBatch $batch): array
    {
        $check = match ($batch->current_stage) {
            IpcBatch::STAGE_STARTUP => $batch->startupCheck,
            IpcBatch::STAGE_FILLING => $batch->fillingCheck,
            IpcBatch::STAGE_PACKING => $batch->packingCheck,
            IpcBatch::STAGE_FINISHED, IpcBatch::STAGE_APPROVAL, IpcBatch::STAGE_PRINT => $batch->finishedCheck ?? $batch->fillingCheck,
            default => null,
        };

        $stages = array_values(array_filter(
            $batch->activeStages(),
            fn (string $stage) => $stage !== IpcBatch::STAGE_COMPLETED,
        ));
        $index = array_search($batch->current_stage, $stages, true);

        return [
            'id' => $batch->id,
            'no_batch' => $batch->no_batch,
            'product_name' => $batch->masterProduct?->product_name,
            'fg_code' => $batch->masterProduct?->fg_code,
            'stage' => $batch->current_stage,
            'stage_label' => self::STAGE_LABELS[$batch->current_stage] ?? $batch->current_stage,
            'step' => $index === false ? null : $index + 1,
            'total_steps' => count($stages),
            'pic' => ($check?->user ?? $batch->creator)?->name,
            'last_activity_at' => ($check?->updated_at ?? $batch->updated_at)?->toIso8601String(),
        ];
    }
}
