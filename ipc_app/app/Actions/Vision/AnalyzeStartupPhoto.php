<?php

namespace App\Actions\Vision;

use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcLog;
use App\Models\User;
use App\Services\Vision\StartupEvaluation;
use App\Services\Vision\StartupVisionEvaluator;
use App\Services\Vision\VisionClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Startup AI cross-check, ADVISORY: reads the Startup Check photo already saved for a field
 * (Label Bulk / IM / Primary Coding), asks the Vision Service, lets StartupVisionEvaluator
 * decide, and writes one ipc_logs row. Never changes the batch, the attachment or any
 * checklist value. The field_type always comes from config (never guessed from the photo).
 */
class AnalyzeStartupPhoto
{
    public function __construct(
        private readonly VisionClient $client,
        private readonly StartupVisionEvaluator $evaluator,
    ) {}

    /**
     * @param  array{mixing_date?: ?string, exp_date?: ?string, shelf_life_months?: ?int, packaging_type?: ?string}  $entered  values typed on the form (not saved yet)
     * @return array{log: IpcLog, evaluation: StartupEvaluation}|null null when there is no photo yet
     */
    public function handle(IpcBatch $batch, User $user, string $field, array $entered): ?array
    {
        $fieldType = config("vision.startup_ai.fields.{$field}");
        abort_if($fieldType === null, 404);

        $attachment = $this->latestPhoto($batch, $field);
        if ($attachment === null || ! Storage::disk('public')->exists($attachment->file_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($attachment->file_path);
        $filename = basename($attachment->file_path);
        $requestId = (string) Str::uuid();

        $result = $this->client->analyze($contents, $filename, $fieldType, $requestId);

        // Tube packaging: also read through the tube path, display only (decision is forced REVIEW).
        $tube = null;
        $isTube = ($entered['packaging_type'] ?? null)
            ? $entered['packaging_type'] === IpcBatch::PACKAGING_TUBE
            : ($result->raw['packaging_type'] ?? null) === 'tube';
        if ($fieldType === 'coding_print' && $isTube) {
            foreach (config('vision.startup_ai.tube_field_types', []) as $tubeType) {
                $tube = $this->client->analyze($contents, $filename, $tubeType, (string) Str::uuid());
            }
        }

        $evaluation = $this->evaluator->evaluate($batch, $fieldType, $result, $entered, $tube);

        $log = IpcLog::create([
            'request_id' => $requestId,
            'ipc_batch_id' => $batch->id,
            'stage' => IpcBatch::STAGE_STARTUP,
            'field_type' => $fieldType,
            'image_path' => $attachment->file_path,
            'vision_status' => $result->status,
            'engine_used' => $result->engineUsed,
            'confidence' => $result->confidence,
            'format_valid' => $result->formatValid,
            'ocr_value' => $evaluation->readSummary,
            'raw_ocr_text' => null,
            'error_reason' => $result->errorReason,
            'match_method' => $result->matchMethod,
            'processing_time_ms' => $result->processingTimeMs,
            'vision_response' => $result->raw ?: null,
            'decision' => $evaluation->decision,
            'decision_reason' => $evaluation->reason,
            'expected_value' => $evaluation->expectedSummary,
            'comparison' => ['field' => $field, 'entered' => $entered, 'items' => $evaluation->items],
            'created_by' => $user->id,
        ]);

        return ['log' => $log, 'evaluation' => $evaluation];
    }

    /** Label Bulk used to be uploaded on Filling Check; batches that already have it there still read it. */
    private function latestPhoto(IpcBatch $batch, string $field): ?IpcAttachment
    {
        $stages = $field === 'date_bulk' ? ['startup', 'filling'] : ['startup'];

        foreach ($stages as $stage) {
            $photo = IpcAttachment::query()
                ->where('ipc_batch_id', $batch->id)
                ->where('stage', $stage)
                ->where('field_label', $field)
                ->latest('id')
                ->first();

            if ($photo) {
                return $photo;
            }
        }

        return null;
    }
}
