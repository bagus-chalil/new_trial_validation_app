<?php

namespace App\Actions\Vision;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Services\Vision\StartupVisionEvaluator;
use App\Services\Vision\VisionResult;
use Illuminate\Support\Facades\Cache;

/**
 * Feeds the "Rekomendasi AI" card: per Startup photo, whether its queued reads are waiting,
 * running or done and, once done, the evaluation against the form values the operator has
 * typed RIGHT NOW (so editing the EXP/mixing date after the photo re-evaluates without re-reading
 * the photo). Advisory only: nothing here changes the batch or blocks saving.
 */
class StartupAiStatus
{
    private const LABELS = [
        'im_number' => 'IM Number',
        'date_bulk' => 'Label Bulk',
        'coding_primary' => 'Primary Coding (Operator)',
        'coding_primary_2' => 'Primary Coding (foto ke-2, sudut lain)',
    ];

    public function __construct(private readonly StartupVisionEvaluator $evaluator) {}

    /**
     * @param  array{mixing_date?: ?string, exp_date?: ?string, shelf_life_months?: ?int, packaging_type?: ?string}  $entered
     * @return array{pending: bool, fields: list<array<string, mixed>>, notes: list<string>}
     */
    public function handle(IpcBatch $batch, array $entered): array
    {
        $fields = [];
        $pending = false;
        $tubeExp = [];

        foreach (array_keys(config('vision.startup_ai.fields', [])) as $field) {
            $attachment = IpcAttachment::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('stage', 'startup')
                ->where('field_label', $field)
                ->latest('id')
                ->first();
            if ($attachment === null) {
                continue;
            }

            $reads = QueueStartupAiReads::readsFor($field, $entered['packaging_type'] ?? null);
            $logs = [];
            $markers = [];
            foreach (array_keys($reads) as $type) {
                $log = IpcLog::query()
                    ->where('ipc_batch_id', $batch->id)
                    ->where('field_type', $type)
                    ->where('image_path', $attachment->file_path)
                    ->latest('id')
                    ->first();
                if ($log !== null) {
                    $logs[$type] = $log;

                    continue;
                }
                $markers[] = Cache::get(QueueStartupAiReads::cacheKey($batch->id, $attachment->id, $type));
            }

            // done = every read has its log row; otherwise running/queued while a marker exists, else
            // 'none' (never queued, or the job died) and the page offers a retry.
            $state = match (true) {
                $markers === [] => 'done',
                in_array('running', $markers, true) => 'running',
                in_array('queued', $markers, true) => 'queued',
                default => 'none',
            };

            $row = ['field' => $field, 'label' => self::LABELS[$field] ?? $field, 'state' => $state];

            if ($state === 'done') {
                $fieldType = array_key_first($reads);
                $tubeType = config('vision.startup_ai.tube_field_types.0');
                $llmType = config('vision.startup_ai.tube_llm_field_type');
                $evaluation = $this->evaluator->evaluate(
                    $batch,
                    $fieldType,
                    $this->resultOf($logs[$fieldType]),
                    $entered,
                    isset($logs[$tubeType]) ? $this->resultOf($logs[$tubeType]) : null,
                    $llmType && isset($logs[$llmType]) ? $this->resultOf($logs[$llmType]) : null,
                );
                $row += [
                    'decision' => $evaluation->decision,
                    'reason' => $evaluation->reason,
                    'items' => $evaluation->items,
                ];
                if ($llmType && isset($logs[$llmType])) {
                    $tubeExp[$field] = $logs[$llmType]->vision_response['exp_raw'] ?? null;
                }
            }

            $pending = $pending || in_array($state, ['queued', 'running'], true);
            $fields[] = $row;
        }

        return ['pending' => $pending, 'fields' => $fields, 'notes' => $this->pairNotes($tubeExp)];
    }

    /** Both tube crimp photos read: say whether the two angles agree (info only, never a decision). */
    private function pairNotes(array $tubeExp): array
    {
        $first = $tubeExp['coding_primary'] ?? null;
        $second = $tubeExp['coding_primary_2'] ?? null;
        if ($first === null || $second === null) {
            return [];
        }

        return [$first === $second
            ? "Foto 1 dan foto ke-2 terbaca sama oleh AI ({$first}). Tetap cek dengan mata."
            : "Foto 1 terbaca {$first}, foto ke-2 terbaca {$second}: beda, cek manual."];
    }

    /** Rebuild the Vision result from a stored log row (ERROR rows have no stored response). */
    private function resultOf(IpcLog $log): VisionResult
    {
        $raw = $log->vision_response;
        if (is_array($raw) && $raw !== []) {
            return VisionResult::fromResponse($raw);
        }

        return VisionResult::error($log->error_reason ?? 'ERROR');
    }
}
