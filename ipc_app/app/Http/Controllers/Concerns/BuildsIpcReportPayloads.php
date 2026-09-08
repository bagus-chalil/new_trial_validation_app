<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FinishedCheckSample;
use App\Models\IpcApproval;
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
        'startup' => ['im_number', 'color', 'temperature_setting', 'coding'],
        'filling' => ['color'],
        'packing' => ['palletisasi', 'color', 'primary_coding_batch_exp', 'tersier_coding_batch', 'secondary_coding_batch_exp'],
        'finished' => ['wi_number', 'exp_date', 'color'],
    ];

    /** temperature_setting accumulates multiple rows; all others are single-photo. */
    private const MULTI_PHOTO_FIELDS = ['temperature_setting'];

    /**
     * @param  list<string>  $stages
     * @return array<string, array<string, string|null|array>>
     */
    private function photoUrls(IpcBatch $batch, array $stages): array
    {
        $fields = collect(self::PHOTOS_BY_STAGE)->only($stages)->all();

        // Group by stage then field_label; multi-photo fields keep all rows, others keep last.
        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->whereIn('stage', array_keys($fields))
            ->orderBy('id')
            ->get()
            ->groupBy('stage')
            ->map(fn ($rows) => $rows->groupBy('field_label'));

        $disk = Storage::disk('public');

        return collect($fields)->mapWithKeys(function (array $fieldList, string $stage) use ($photos, $disk) {
            $stagePhotos = $photos->get($stage, collect());

            return [$stage => collect($fieldList)->mapWithKeys(function (string $field) use ($stagePhotos, $disk) {
                $rows = $stagePhotos->get($field, collect());
                if (in_array($field, self::MULTI_PHOTO_FIELDS, true)) {
                    return [$field => $rows->map(fn ($a) => $disk->url($a->file_path))->values()->all()];
                }

                return [$field => $rows->isNotEmpty() ? $disk->url($rows->last()->file_path) : null];
            })->all()];
        })->all();
    }

    /**
     * Browsershot renders a standalone HTML string with no HTTP context, so a relative/public
     * URL is not guaranteed to load reliably — embed each photo directly as a data: URI
     * instead, same approach as TrialReportController::attachmentDataUri() in the sibling app.
     *
     * @param  list<string>  $stages
     * @return array<string, array<string, string|null|array>>
     */
    private function photoDataUris(IpcBatch $batch, array $stages): array
    {
        $fields = collect(self::PHOTOS_BY_STAGE)->only($stages)->all();

        $photos = IpcAttachment::query()
            ->where('ipc_batch_id', $batch->id)
            ->whereIn('stage', array_keys($fields))
            ->orderBy('id')
            ->get()
            ->groupBy('stage')
            ->map(fn ($rows) => $rows->groupBy('field_label'));

        $disk = Storage::disk('public');

        return collect($fields)->mapWithKeys(function (array $fieldList, string $stage) use ($photos, $disk) {
            $stagePhotos = $photos->get($stage, collect());

            return [$stage => collect($fieldList)->mapWithKeys(function (string $field) use ($stagePhotos, $disk) {
                $rows = $stagePhotos->get($field, collect());
                if (in_array($field, self::MULTI_PHOTO_FIELDS, true)) {
                    return [$field => $rows->map(function ($a) use ($disk) {
                        if (! $disk->exists($a->file_path)) {
                            return null;
                        }
                        $mime = $disk->mimeType($a->file_path) ?: 'image/jpeg';

                        return 'data:'.$mime.';base64,'.base64_encode($disk->get($a->file_path));
                    })->values()->all()];
                }

                $row = $rows->last();
                if (! $row || ! $disk->exists($row->file_path)) {
                    return [$field => null];
                }
                $mime = $disk->mimeType($row->file_path) ?: 'image/jpeg';

                return [$field => 'data:'.$mime.';base64,'.base64_encode($disk->get($row->file_path))];
            })->all()];
        })->all();
    }

    /**
     * Per-TH_PROGRESS-round photo state for Packing Check's checklist-linked photo fields
     * (PackingCheck::PHOTO_FIELD_BY_CHECKLIST_FIELD), keyed by PackingCheckRevision id — unlike
     * photoDataUris() above, which only ever returns the single *current* photo per field.
     * Packing is the only stage whose printed report needs this: its checklist rows repeat one
     * column per round, and each round should show the photo that was actually current at the
     * moment that round was saved (see SavePackingCheck::handle(), which writes one
     * PackingCheckRevisionPhoto per field per save), not whatever the latest upload happens to
     * be by the time the report is printed. Expects 'packingCheck.revisions.photos' to already
     * be eager-loaded by the caller — this method issues no query of its own.
     *
     * @return array<int, array<string, string|null>>
     */
    private function packingRevisionPhotoUris(IpcBatch $batch): array
    {
        $disk = Storage::disk('public');

        return ($batch->packingCheck?->revisions ?? collect())
            ->mapWithKeys(fn ($revision) => [
                $revision->id => $revision->photos->mapWithKeys(function ($photo) use ($disk) {
                    if (! $disk->exists($photo->file_path)) {
                        return [$photo->field_label => null];
                    }

                    $mime = $disk->mimeType($photo->file_path) ?: 'image/jpeg';

                    return [$photo->field_label => 'data:'.$mime.';base64,'.base64_encode($disk->get($photo->file_path))];
                })->all(),
            ])->all();
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

    /**
     * Resolves the Blade view + data + filename for one stage's report PDF — shared by
     * ApprovalController::print() (a non-logged preview) and PrintController's own preview()/
     * pdf() (the latter also logs an IpcPrintLog row, layered on by the caller). Extracted
     * 2026-09-08 so the two controllers' identical eager-load/payload blocks can't drift apart the
     * way the report payload builders above once could before this trait existed.
     *
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function resolveReportPdf(IpcBatch $batch, string $stage): array
    {
        return match ($stage) {
            IpcApproval::STAGE_STARTUP => [
                'pdf.approval-startup',
                (function () use ($batch) {
                    $batch->load(['startupCheck.user', 'startupInspection.items', 'startupInspection.samples', 'startupInspection.testResults.testType']);

                    return $this->startupPayload($batch, $this->photoDataUris($batch, ['startup']));
                })(),
                "Startup-Inspection-{$batch->no_batch}.pdf",
            ],
            IpcApproval::STAGE_FILLING_PACKING => [
                'pdf.approval-filling-packing',
                (function () use ($batch) {
                    $batch->load([
                        'startupCheck',
                        'fillingCheck.user',
                        'fillingCheck.samples',
                        'fillingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
                        'fillingCheck.revisions.user',
                        'fillingCheck.revisions.samples',
                        'packingCheck.user',
                        'packingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
                        'packingCheck.revisions.user',
                        'packingCheck.revisions.photos',
                    ]);

                    return [
                        ...$this->fillingPackingPayload($batch, $this->photoDataUris($batch, ['filling', 'packing'])),
                        'packingRevisionPhotoUris' => $this->packingRevisionPhotoUris($batch),
                    ];
                })(),
                "Filling-Packing-Report-{$batch->no_batch}.pdf",
            ],
            IpcApproval::STAGE_FINISHED => [
                'pdf.approval-finished',
                (function () use ($batch) {
                    $batch->load([
                        'finishedCheck.user',
                        'finishedCheck.samples',
                        'finishedCheck.revisions' => fn ($query) => $query->latest('revision_no'),
                        'finishedCheck.revisions.user',
                        'finishedCheck.revisions.samples',
                    ]);

                    return $this->finishedPayload($batch, $this->photoDataUris($batch, ['finished']));
                })(),
                "Finished-Good-Report-{$batch->no_batch}.pdf",
            ],
            default => abort(404),
        };
    }
}
