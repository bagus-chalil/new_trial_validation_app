<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FinishedCheckSample;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\MasterTestType;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\StartupInspectionItem;
use Illuminate\Support\Facades\Storage;

/**
 * Shared read-only report payload builders for the three stage sections (Startup, Filling &
 * Packing, Finished) — used by both ApprovalController and PrintController so the two report
 * views (Approve vs. View/Print, matching legacy's *_Approval vs. *_View screens) can never drift
 * out of sync with each other. Extracted from ApprovalController 2026-09-04 when PrintController
 * was built needing the exact same payloads.
 */
trait BuildsIpcReportPayloads
{
    /**
     * Photo fields per stage, mirroring each stage controller's own PHOTO_FIELDS — duplicated
     * here (not imported) since this report spans all four check controllers.
     */
    private const PHOTOS_BY_STAGE = [
        'startup' => ['im_number', 'color', 'temperature_setting'],
        'filling' => ['color'],
        'packing' => ['palletisasi', 'color', 'primary_coding_batch_exp', 'tersier_coding_batch', 'secondary_coding_batch_exp'],
        'finished' => ['wi_number', 'exp_date', 'color'],
    ];

    /**
     * @param  list<string>  $stages
     * @return array<string, array<string, string|null>>
     */
    private function photoUrls(IpcBatch $batch, array $stages): array
    {
        $fields = collect(self::PHOTOS_BY_STAGE)->only($stages)->all();

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->whereIn('stage', array_keys($fields))
            ->get()
            ->groupBy('stage')
            ->map(fn ($rows) => $rows->keyBy('field_label'));

        return collect($fields)->mapWithKeys(function (array $fieldList, string $stage) use ($photos) {
            $stagePhotos = $photos->get($stage, collect());

            return [$stage => collect($fieldList)->mapWithKeys(fn (string $field) => [
                $field => $stagePhotos->has($field) ? Storage::disk('public')->url($stagePhotos[$field]->file_path) : null,
            ])->all()];
        })->all();
    }

    /**
     * Browsershot renders a standalone HTML string with no HTTP context, so a relative/public
     * URL is not guaranteed to load reliably — embed each photo directly as a data: URI
     * instead, same approach as TrialReportController::attachmentDataUri() in the sibling app.
     *
     * @param  list<string>  $stages
     * @return array<string, array<string, string|null>>
     */
    private function photoDataUris(IpcBatch $batch, array $stages): array
    {
        $fields = collect(self::PHOTOS_BY_STAGE)->only($stages)->all();

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->whereIn('stage', array_keys($fields))
            ->get()
            ->groupBy('stage')
            ->map(fn ($rows) => $rows->keyBy('field_label'));

        $disk = Storage::disk('public');

        return collect($fields)->mapWithKeys(function (array $fieldList, string $stage) use ($photos, $disk) {
            $stagePhotos = $photos->get($stage, collect());

            return [$stage => collect($fieldList)->mapWithKeys(function (string $field) use ($stagePhotos, $disk) {
                if (! $stagePhotos->has($field) || ! $disk->exists($stagePhotos[$field]->file_path)) {
                    return [$field => null];
                }

                $path = $stagePhotos[$field]->file_path;
                $mime = $disk->mimeType($path) ?: 'image/jpeg';

                return [$field => 'data:'.$mime.';base64,'.base64_encode($disk->get($path))];
            })->all()];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function startupPayload(IpcBatch $batch, array $photoUrls): array
    {
        $testTypesByCategory = MasterTestType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $testResultsByTypeId = $batch->startupInspection?->testResults->keyBy('master_test_type_id') ?? collect();

        return [
            'startupCheck' => $batch->startupCheck,
            'startupInspection' => $batch->startupInspection,
            'photoUrls' => $photoUrls,
            'startupChecklistGroups' => StartupCheck::checklistGroups(),
            'startupInspectionParameterKeys' => StartupInspectionItem::PARAMETER_KEYS,
            'testTypesByCategory' => $testTypesByCategory->map(fn ($rows) => $rows->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'is_performed' => (bool) ($testResultsByTypeId->get($type->id)?->is_performed ?? false),
            ])->values()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fillingPackingPayload(IpcBatch $batch, array $photoUrls): array
    {
        return [
            'startupCheck' => $batch->startupCheck,
            'fillingCheck' => $batch->fillingCheck,
            'packingCheck' => $batch->packingCheck,
            'photoUrls' => $photoUrls,
            'packingChecklistGroups' => PackingCheck::checklistGroups(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finishedPayload(IpcBatch $batch, array $photoUrls): array
    {
        return [
            'finishedCheck' => $batch->finishedCheck,
            'photoUrls' => $photoUrls,
            'finishedSampleGroups' => FinishedCheckSample::sampleGroups(),
        ];
    }
}
