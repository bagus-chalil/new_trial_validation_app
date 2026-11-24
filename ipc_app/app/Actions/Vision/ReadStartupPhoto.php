<?php

namespace App\Actions\Vision;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Services\Vision\CodingFormulaRead;
use App\Services\Vision\PhotoQuality;
use App\Services\Vision\VisionClient;
use App\Services\Vision\VisionEvaluation;
use App\Services\Vision\VisionResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One queued Vision read of a saved Startup Check photo, written as one ipc_logs row. It only
 * READS: no comparison with the form happens here (the operator may still be typing), that is
 * computed when the "Rekomendasi AI" card is shown (see StartupAiStatus). The row's decision is
 * always REVIEW; it never changes the batch, the attachment or any checklist value.
 *
 * `coding_formula` (Primary Coding): photo 1 + the optional 2nd photo go in ONE call (`file2`) with the
 * SKU's formula (or "auto"); the log keeps the 2nd photo's path (comparison.image_path_2) so a later
 * 2nd photo triggers a fresh read of the pair.
 */
class ReadStartupPhoto
{
    /** engine_used of a log written by the pre-check; excluded from accuracy feedback. */
    public const PRECHECK_ENGINE = 'photo_precheck';

    public function __construct(private readonly VisionClient $client) {}

    /**
     * @param  ?string  $hint  operator feedback ("Beri masukan ke AI"): where/how to look in this same photo. It only
     *                         steers the Vision Service's reading; it is never taken as the value itself.
     */
    public function handle(IpcBatch $batch, IpcAttachment $attachment, string $field, string $fieldType, ?int $userId, ?IpcAttachment $second = null, ?string $hint = null): ?IpcLog
    {
        if (! Storage::disk('public')->exists($attachment->file_path)) {
            return null;
        }

        $hint = $hint !== null ? (Str::of($hint)->squish()->limit(300, '')->toString() ?: null) : null;

        $isFormula = $fieldType === QueueStartupAiReads::CODING_FORMULA;
        // A 2nd photo whose file is gone is simply not sent (the read still records which pair it was for).
        $secondFile = $isFormula && $second !== null && Storage::disk('public')->exists($second->file_path)
            ? ['contents' => Storage::disk('public')->get($second->file_path), 'filename' => basename($second->file_path)]
            : null;
        $formula = $isFormula ? $batch->loadMissing('masterProduct')->masterProduct?->codingFormulaForVision() ?? 'auto' : null;
        $pair = $isFormula ? ['image_path_2' => $second?->file_path, 'formula_sent' => $formula] : [];

        $requestId = (string) Str::uuid();
        $bytes = Storage::disk('public')->get($attachment->file_path);

        // A blank / almost black photo (failed capture) is never worth a GPU read: log it as LOW with a
        // precise reason so the card can say "foto kosong, ambil ulang" instead of "belum yakin".
        $quality = app(PhotoQuality::class)->assess($bytes);
        if ($quality !== null && $quality['blank']) {
            return IpcLog::create([
                'request_id' => $requestId,
                'ipc_batch_id' => $batch->id,
                'stage' => IpcBatch::STAGE_STARTUP,
                'field_type' => $fieldType,
                'image_path' => $attachment->file_path,
                'vision_status' => 'LOW_CONFIDENCE',
                'engine_used' => self::PRECHECK_ENGINE,
                'confidence' => 0.0,
                'error_reason' => 'PHOTO_BLANK',
                'vision_response' => ['status' => 'LOW_CONFIDENCE', 'confidence' => 0.0, 'engine_used' => self::PRECHECK_ENGINE, 'photo_quality' => $quality],
                'decision' => VisionEvaluation::REVIEW,
                'decision_reason' => 'Foto kosong / terlalu gelap, tidak dikirim ke Vision Service.',
                'comparison' => ['field' => $field, 'queued' => true, 'precheck' => 'blank', ...$pair],
                'created_by' => $userId,
            ]);
        }

        // The read we already have for this photo (pair): a hinted re-read must not make things worse than that.
        $previous = $hint !== null ? QueueStartupAiReads::findLog($batch, $attachment, $fieldType, $second) : null;

        $result = $this->client->analyze(
            $bytes,
            basename($attachment->file_path),
            $fieldType,
            $requestId,
            $secondFile,
            [
                ...($isFormula ? ['coding_formula' => $formula] : []),
                ...($hint !== null ? ['user_hint' => $hint] : []),
            ],
        );

        $elapsed = $result->raw['elapsed_sec'] ?? null;
        $comparison = ['field' => $field, 'queued' => true, ...$pair];

        if ($hint !== null) {
            $comparison['user_hint'] = $hint;
            $comparison['hint_discarded'] = $this->score($result) < ($previous !== null ? $this->score($this->resultOf($previous)) : 0);
        }

        if ($isFormula) {
            $comparison += $this->formulaComparison($batch, $result);
        }

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
            'ocr_value' => $isFormula ? CodingFormulaRead::summary($result->raw) : ($result->raw['exp_raw'] ?? $result->value),
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

    /**
     * How much of the photo the Vision Service could read with confidence: the number of fields it trusts
     * (coding_formula), else 1 for an OK read. An ERROR scores -1, so a failed hinted re-read (service down)
     * never replaces a read that worked. Used only to decide whether a hinted re-read replaces the old read.
     */
    private function score(VisionResult $result): int
    {
        if ($result->status === VisionResult::STATUS_ERROR) {
            return -1;
        }

        $fields = $result->raw['fields'] ?? null;
        if (is_array($fields)) {
            return collect($fields)->filter(fn ($f) => is_array($f) && ($f['status'] ?? null) === 'OK')->count();
        }

        return $result->status === VisionResult::STATUS_OK ? 1 : 0;
    }

    private function resultOf(IpcLog $log): VisionResult
    {
        $raw = $log->vision_response;

        return is_array($raw) && $raw !== [] ? VisionResult::fromResponse($raw) : VisionResult::error($log->error_reason ?? 'ERROR');
    }

    /**
     * Measurement data for the advisory phase: which formula was used, what the AI read (as-is) and
     * how it compares to what is saved on the batch now (the operator's final values are paired later
     * by RecordStartupAiFeedback). Never a decision.
     *
     * @return array<string, mixed>
     */
    private function formulaComparison(IpcBatch $batch, VisionResult $result): array
    {
        $raw = $result->raw;
        $batchField = CodingFormulaRead::field($raw, 'batch');
        $expField = CodingFormulaRead::field($raw, 'exp');
        $dbBatch = CodingFormulaRead::batchLetters($batch->no_batch);
        $aiExp = CodingFormulaRead::expKey($expField);
        $dbExp = CodingFormulaRead::referenceKey($batch->exp_date?->format('Y-m-d'), $expField['granularity'] ?? null);
        $aiBatch = $batchField['value'] ?? null;

        return [
            'formula_source' => $raw['formula_source'] ?? null,
            'ai' => [
                'batch' => $aiBatch,
                'batch_status' => $batchField['status'] ?? null,
                'exp' => $expField['value'] ?? null,
                'exp_key' => $aiExp,
                'exp_status' => $expField['status'] ?? null,
                'granularity' => $expField['granularity'] ?? null,
            ],
            'db' => ['batch' => $dbBatch, 'exp_key' => $dbExp],
            'batch_differs' => CodingFormulaRead::isOk($batchField) && $aiBatch !== null && $dbBatch !== null
                ? strtoupper(trim((string) $aiBatch)) !== $dbBatch : null,
            'exp_differs' => CodingFormulaRead::isOk($expField) && $aiExp !== null && $dbExp !== null ? $aiExp !== $dbExp : null,
        ];
    }
}
