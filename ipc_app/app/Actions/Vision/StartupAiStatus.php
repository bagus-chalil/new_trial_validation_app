<?php

namespace App\Actions\Vision;

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
            // coding_formula: the 2nd Primary Coding photo has no read of its own (it rides along as file2).
            $reads = QueueStartupAiReads::readsFor($field, $entered['packaging_type'] ?? null);
            if ($reads === []) {
                continue;
            }

            $attachment = QueueStartupAiReads::latestPhoto($batch, $field);
            if ($attachment === null) {
                continue;
            }

            $logs = [];
            $markers = [];
            $second = null;
            foreach (array_keys($reads) as $type) {
                $pairPhoto = QueueStartupAiReads::secondPhotoFor($batch, $field, $type);
                $second ??= $pairPhoto;
                $log = QueueStartupAiReads::findLog($batch, $attachment, $type, $pairPhoto);
                if ($log !== null) {
                    $logs[$type] = $log;

                    continue;
                }
                $markers[] = Cache::get(QueueStartupAiReads::cacheKey($batch->id, $attachment->id, $type, $pairPhoto?->id));
            }

            // done = every read has its log row; otherwise running/queued while a marker exists, else
            // 'none' (never queued, or the job died) and the page offers a retry.
            $state = match (true) {
                $markers === [] => 'done',
                in_array('running', $markers, true) => 'running',
                in_array('queued', $markers, true) => 'queued',
                default => 'none',
            };

            $label = self::LABELS[$field] ?? $field;
            if ($second !== null) {
                $label = 'Primary Coding (foto 1 + foto 2, dibaca bersama)';
            }
            $row = ['field' => $field, 'label' => $label, 'state' => $state];

            if ($state === 'done') {
                $fieldType = array_key_first($reads);
                $tubeType = config('vision.startup_ai.tube_field_types.0');
                $llmType = config('vision.startup_ai.tube_llm_field_type');
                $mainResult = $this->resultOf($logs[$fieldType]);
                $evaluation = $this->evaluator->evaluate(
                    $batch,
                    $fieldType,
                    $mainResult,
                    $entered,
                    isset($logs[$tubeType]) ? $this->resultOf($logs[$tubeType]) : null,
                    $llmType && isset($logs[$llmType]) ? $this->resultOf($logs[$llmType]) : null,
                );
                $row += [
                    'decision' => $evaluation->decision,
                    'reason' => $evaluation->reason,
                    'items' => $evaluation->items,
                    // A photo with any part that needs retaking is not reliable enough to prefill from.
                    'suggestions' => collect($evaluation->items)->contains('retake', true)
                        ? []
                        : $this->evaluator->suggestions($fieldType, $mainResult, $entered),
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
