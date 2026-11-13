<?php

namespace App\Actions\Vision;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Services\Vision\VisionClient;
use App\Services\Vision\VisionEvaluation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One queued Vision read of a saved Startup Check photo, written as one ipc_logs row. It only
 * READS: no comparison with the form happens here (the operator may still be typing), that is
 * computed when the "Rekomendasi AI" card is shown (see StartupAiStatus). The row's decision is
 * always REVIEW; it never changes the batch, the attachment or any checklist value.
 */
class ReadStartupPhoto
{
    public function __construct(private readonly VisionClient $client) {}

    public function handle(IpcBatch $batch, IpcAttachment $attachment, string $field, string $fieldType, ?int $userId): ?IpcLog
    {
        if (! Storage::disk('public')->exists($attachment->file_path)) {
            return null;
        }

        $requestId = (string) Str::uuid();
        $result = $this->client->analyze(
            Storage::disk('public')->get($attachment->file_path),
            basename($attachment->file_path),
            $fieldType,
            $requestId,
        );

        $elapsed = $result->raw['elapsed_sec'] ?? null;
        $comparison = ['field' => $field, 'queued' => true];

        if ($fieldType === config('vision.startup_ai.tube_llm_field_type')) {
            // Accuracy re-measurement on real photos: what is saved in the DB vs what the AI read, and a flag
            // for "AI said OK but differs from the DB EXP" (check by eye: a misread, or the print really differs).
            $read = $result->raw['exp_raw'] ?? null;
            $dbExp = $batch->exp_date?->format('dmy');
            $comparison += [
                'advisory' => true,
                'db_exp' => $dbExp,
                'ai_ok_but_differs' => $result->status === 'OK' && $read !== null && $dbExp !== null && $read !== $dbExp,
            ];
        }

        return IpcLog::create([
            'request_id' => $requestId,
            'ipc_batch_id' => $batch->id,
            'stage' => IpcBatch::STAGE_STARTUP,
            'field_type' => $fieldType,
            'image_path' => $attachment->file_path,
            'vision_status' => $result->status,
            'engine_used' => $result->engineUsed,
            'confidence' => $result->confidence,
            'format_valid' => $result->formatValid,
            'ocr_value' => $result->raw['exp_raw'] ?? $result->value,
            'raw_ocr_text' => null,
            'error_reason' => $result->errorReason,
            'match_method' => $result->matchMethod,
            'processing_time_ms' => $result->processingTimeMs ?? ($elapsed !== null ? (int) round($elapsed * 1000) : null),
            'vision_response' => $result->raw ?: null,
            'decision' => VisionEvaluation::REVIEW,
            'decision_reason' => 'Dibaca otomatis lewat antrean; perbandingan dengan form dihitung saat ditampilkan.',
            'comparison' => $comparison,
            'created_by' => $userId,
        ]);
    }
}
