<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FinishedCheckSample;
use App\Models\IpcApproval;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\IpcReportDocument;
use App\Models\MasterTestType;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\StartupInspectionItem;
use App\Services\Verification\VerificationQrCode;
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
        'startup' => ['im_number', 'color', 'temperature_setting', 'coding_primary', 'coding_secondary', 'coding_tersier'],
        'filling' => ['color', 'wo_image', 'date_bulk', 'image_tube'],
        'packing' => ['palletisasi', 'color', 'primary_coding_batch_exp', 'tersier_coding_batch', 'secondary_coding_batch_exp'],
        'finished' => ['wi_number', 'exp_date', 'color'],
    ];

    /** temperature_setting accumulates multiple rows; all others are single-photo. */
    private const MULTI_PHOTO_FIELDS = ['temperature_setting', 'wi_number'];

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
        $testResultsByTypeId = $batch->startupInspection?->testResults->keyBy('master_test_type_id') ?? collect();
        $performedTypeIds = $testResultsByTypeId->filter(fn ($result) => $result->is_performed)->keys();

        // Active types, plus any this batch recorded as performed even if since deactivated/deleted,
        // in the form's Leakage → Functional → Attribute order.
        $testTypesByCategory = MasterTestType::withTrashed()
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at'))
                ->orWhereIn('id', $performedTypeIds))
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->sortBy(fn ($rows, $category) => array_search($category, MasterTestType::CATEGORIES, true) === false ? 99 : array_search($category, MasterTestType::CATEGORIES, true));

        return [
            'startupCheck' => $batch->startupCheck,
            'startupInspection' => $batch->startupInspection,
            'startupApproval' => IpcApproval::forReport($batch->approvals, IpcApproval::STAGE_STARTUP),
            'verificationUrl' => VerificationQrCode::url($batch, IpcApproval::STAGE_STARTUP),
            'verificationQr' => VerificationQrCode::svg($batch, IpcApproval::STAGE_STARTUP),
            'photoUrls' => $photoUrls,
            'startupChecklistGroups' => StartupCheck::checklistGroupsFor($batch),
            'startupInspectionParameterKeys' => StartupInspectionItem::parameterKeysFor($batch),
            'testTypesByCategory' => $testTypesByCategory->map(fn ($rows) => $rows->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'label' => $type->label,
                'is_performed' => (bool) ($testResultsByTypeId->get($type->id)?->is_performed ?? false),
            ])->values()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fillingPackingPayload(IpcBatch $batch, array $photoUrls, string $side): array
    {
        $isFilling = $side === IpcApproval::STAGE_FILLING;
        // 'both' is the combined FR.QAC.193.00 document (flow Lengkap only, see
        // "filling_packing_fg" branch of resolveReportPdf()) — renders Filling AND Packing in one
        // PDF instead of this report's usual one-side-per-document split.
        $includeFilling = $side === 'both' || $isFilling;
        $includePacking = $side === 'both' || $side === IpcApproval::STAGE_PACKING;

        return [
            'side' => $side,
            'startupCheck' => $batch->startupCheck,
            'startupChecklistGroups' => StartupCheck::checklistGroupsFor($batch),
            // Paper form's "VERIFIKASI SEBELUM PRODUKSI" grid (Filling only) is per-sample per
            // parameter; this app's Start Inspection only captures one status per parameter for
            // the whole batch (StartupInspectionItem), not per sample — see
            // startup_inspection_items' doc comment. The Filling side of this report renders that
            // coarser summary rather than fabricating 30 blank sample columns.
            'startupInspection' => $batch->startupInspection,
            'startupInspectionParameterKeys' => StartupInspectionItem::parameterKeysFor($batch),
            'fillingCheck' => $includeFilling ? $batch->fillingCheck : null,
            'packingCheck' => $includePacking ? $batch->packingCheck : null,
            'photoUrls' => $photoUrls,
            'packingChecklistGroups' => PackingCheck::checklistGroups(),
            // Single-side keys (kept as-is for the existing standalone Filling/Packing reports).
            'reportApproval' => $side !== 'both' ? $batch->approvals->firstWhere('stage', $side) : null,
            'verificationUrl' => $side !== 'both' ? VerificationQrCode::url($batch, $side) : null,
            'verificationQr' => $side !== 'both' ? VerificationQrCode::svg($batch, $side) : null,
            // Per-side keys, always populated, used by the combined 'both' document so each
            // section can show its own Issued By / Review By / QR instead of collapsing two real,
            // separately-timed approvals into one signature box.
            'fillingApproval' => $includeFilling ? $batch->approvals->firstWhere('stage', IpcApproval::STAGE_FILLING) : null,
            'packingApproval' => $includePacking ? $batch->approvals->firstWhere('stage', IpcApproval::STAGE_PACKING) : null,
            'fillingVerificationQr' => $includeFilling ? VerificationQrCode::svg($batch, IpcApproval::STAGE_FILLING) : null,
            'packingVerificationQr' => $includePacking ? VerificationQrCode::svg($batch, IpcApproval::STAGE_PACKING) : null,
        ];
    }

    /**
     * Eager loads for one side of the split Filling / Packing report.
     *
     * @return array<int|string, mixed>
     */
    private function fillingPackingRelations(string $side): array
    {
        if ($side === IpcApproval::STAGE_FILLING) {
            return [
                'fillingCheck.user',
                'fillingCheck.samples',
                'fillingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
                'fillingCheck.revisions.user',
                'fillingCheck.revisions.samples',
            ];
        }

        return [
            'packingCheck.user',
            'packingCheck.masterLine',
            'packingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'packingCheck.revisions.user',
            'packingCheck.revisions.photos',
            'packingCheck.revisions.masterLine',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finishedPayload(IpcBatch $batch, array $photoUrls): array
    {
        $batch->loadMissing('microCheck.user');

        return [
            'finishedCheck' => $batch->finishedCheck,
            'microCheck' => $batch->microCheck,
            'photoUrls' => $photoUrls,
            'finishedSampleGroups' => FinishedCheckSample::sampleGroups(),
            'finishedApproval' => $batch->approvals->firstWhere('stage', IpcApproval::STAGE_FINISHED),
            'verificationUrl' => VerificationQrCode::url($batch, IpcApproval::STAGE_FINISHED),
            'verificationQr' => VerificationQrCode::svg($batch, IpcApproval::STAGE_FINISHED),
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
        // Packing + FG prints as 3 forms: Startup (FR.QAC.182.03), Packing (FR.QAC.193.00), Finished.
        if ($batch->flow_type === IpcBatch::FLOW_PACKING_FG && in_array($stage, [IpcApproval::STAGE_STARTUP, IpcApproval::STAGE_PACKING], true)) {
            return $this->resolvePackingFgPart($batch, $stage);
        }

        return match ($stage) {
            IpcApproval::STAGE_STARTUP => [
                'pdf.approval-startup',
                (function () use ($batch) {
                    $batch->load(['startupCheck.user', 'startupInspection.user', 'startupInspection.items', 'startupInspection.samples', 'startupInspection.testResults', 'approvals.approver']);

                    return [
                        ...$this->startupPayload($batch, $this->photoDataUris($batch, ['startup'])),
                        'title' => 'Start Up Inspection Form',
                    ];
                })(),
                "Startup-Inspection-{$batch->no_batch}.pdf",
            ],
            IpcApproval::STAGE_FILLING, IpcApproval::STAGE_PACKING => [
                'pdf.approval-filling-packing',
                (function () use ($batch, $stage) {
                    abort_unless(in_array($stage, $batch->activeStages(), true), 404);

                    $batch->load([
                        'startupCheck',
                        'startupCheck.user',
                        'startupInspection.items',
                        // Packing's "PEMERIKSAAN BERAT PRODUK" section (paper form FR.QAC.182.03)
                        // reuses the same per-sample Volume/Berat + Berat Masterbox readings
                        // captured once at Start Inspection — there is no separate per-sample
                        // weight capture on packing_checks itself (only an aggregate
                        // standard_weight_mb/sum_weight_mb pair), so this is the real underlying
                        // data, not a fabrication.
                        'startupInspection.samples',
                        ...$this->fillingPackingRelations($stage),
                        'approvals.approver',
                    ]);

                    $isFilling = $stage === IpcApproval::STAGE_FILLING;

                    // Filling and Packing are now separate documents — each maps onto its own
                    // real paper form (FR.QAC.192.02 / FR.QAC.182.03), tracked in its own
                    // ipc_report_documents row, editable by QAC staff via Master > Dokumen Laporan.
                    $reportDocument = IpcReportDocument::forKey($isFilling ? IpcReportDocument::KEY_FILLING : IpcReportDocument::KEY_PACKING);

                    return [
                        // 'startup' is included so the shared pdf._startup-checklist partial can
                        // render the Startup Check's IM Number / Warna / Hasil Coding photos —
                        // both FR.QAC.192.02 and FR.QAC.182.03 show them at the top of this same
                        // document, not just on the standalone Startup report.
                        ...$this->fillingPackingPayload($batch, $this->photoDataUris($batch, ['startup', $stage]), $stage),
                        'packingRevisionPhotoUris' => $isFilling ? [] : $this->packingRevisionPhotoUris($batch),
                        'title' => $reportDocument?->title ?? 'Laporan Pemeriksaan In Process Control '.($isFilling ? 'Filling' : 'Packing'),
                        'docNumber' => $reportDocument?->doc_number ?? ($isFilling ? 'FR.QAC.192.02' : 'FR.QAC.182.03'),
                        'docRevision' => $reportDocument?->revision ?? ($isFilling ? '02' : '03'),
                    ];
                })(),
                ($stage === IpcApproval::STAGE_FILLING ? 'Filling' : 'Packing')."-Report-{$batch->no_batch}.pdf",
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
                        'approvals.approver',
                    ]);

                    // Finished Good has no standalone paper form of its own — it only ever appears
                    // as page 2 of FR.QAC.193.00 (confirmed against docs/report/: FR.QAC.182.03,
                    // the standalone Packing form, does NOT include FG at all) — so this always
                    // reads the same filling_packing_fg metadata row, for every flow that has a
                    // Finished Check (full and packing_fg both).
                    $reportDocument = IpcReportDocument::forKey(IpcReportDocument::KEY_FILLING_PACKING_FG);

                    return [
                        ...$this->finishedPayload($batch, $this->photoDataUris($batch, ['finished'])),
                        // Real page 2 title is its own ("Laporan Pemeriksaan Finished Good"), not
                        // page 1's — only the doc number/revision are shared (same form, 2 pages).
                        'title' => 'Laporan Pemeriksaan Finished Good',
                        'docNumber' => $reportDocument?->doc_number ?? 'FR.QAC.193.00',
                        'docRevision' => $reportDocument?->revision ?? '0',
                    ];
                })(),
                "Finished-Good-Report-{$batch->no_batch}.pdf",
            ],
            // Combined Filling + Packing document (FR.QAC.193.00 page 1) — additive to, not a
            // replacement for, the two standalone Filling/Packing documents above: flow Lengkap
            // (full) is the only flow where the real paper record is this ONE combined form rather
            // than two separate ones, so it's offered as an extra "Cetak Gabungan" option on the
            // Print hub instead of changing what the per-stage Approval/Print pages already do.
            IpcReportDocument::KEY_FILLING_PACKING_FG => [
                'pdf.approval-fillpack',
                (function () use ($batch) {
                    abort_unless($batch->flow_type === IpcBatch::FLOW_FULL, 404, 'Laporan gabungan Filling & Packing hanya tersedia untuk alur Lengkap.');
                    abort_unless(
                        in_array(IpcApproval::STAGE_FILLING, $batch->activeStages(), true)
                            && in_array(IpcApproval::STAGE_PACKING, $batch->activeStages(), true),
                        404
                    );

                    $batch->load([
                        'startupCheck',
                        'startupCheck.user',
                        'startupInspection.items',
                        'startupInspection.samples',
                        ...$this->fillingPackingRelations(IpcApproval::STAGE_FILLING),
                        ...$this->fillingPackingRelations(IpcApproval::STAGE_PACKING),
                        // Page 2 of the combined document is Finished Good (landscape).
                        'finishedCheck.user',
                        'finishedCheck.samples',
                        'finishedCheck.revisions' => fn ($query) => $query->latest('revision_no'),
                        'finishedCheck.revisions.user',
                        'finishedCheck.revisions.samples',
                        'approvals.approver',
                    ]);

                    $reportDocument = IpcReportDocument::forKey(IpcReportDocument::KEY_FILLING_PACKING_FG);

                    return [
                        ...$this->fillingPackingPayload($batch, $this->photoDataUris($batch, ['startup', IpcApproval::STAGE_FILLING, IpcApproval::STAGE_PACKING, IpcApproval::STAGE_FINISHED]), 'both'),
                        'microCheck' => $batch->microCheck,
                        'finishedCheck' => $batch->finishedCheck,
                        'finishedSampleGroups' => FinishedCheckSample::sampleGroups(),
                        'finishedApproval' => $batch->approvals->firstWhere('stage', IpcApproval::STAGE_FINISHED),
                        'finishedVerificationQr' => VerificationQrCode::svg($batch, IpcApproval::STAGE_FINISHED),
                        'packingRevisionPhotoUris' => $this->packingRevisionPhotoUris($batch),
                        'title' => $reportDocument?->title ?? 'Laporan Pemeriksaan In Process Control Filling & Packing',
                        'docNumber' => $reportDocument?->doc_number ?? 'FR.QAC.193.00',
                        'docRevision' => $reportDocument?->revision ?? '0',
                    ];
                })(),
                "Filling-Packing-Report-{$batch->no_batch}.pdf",
            ],
            default => abort(404),
        };
    }

    /**
     * Flow Packing + FG only: the old single Packing PDF split in two (user decision 2026-10-28).
     * 'startup' = Form Pemeriksaan Start Up + Format Coding + Berat Produk, numbered
     * FR.QAC.182.03 (the `startup` ipc_report_documents row); 'packing' =
     * Packing Inspection + Detail Pemeriksaan Kemasan + history, numbered FR.QAC.193.00 (the
     * `filling_packing_fg` row, same number as the Finished Good page that follows it).
     *
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function resolvePackingFgPart(IpcBatch $batch, string $part): array
    {
        $batch->load([
            'startupCheck',
            'startupCheck.user',
            'startupInspection.items',
            'startupInspection.samples',
            ...$this->fillingPackingRelations(IpcApproval::STAGE_PACKING),
            'approvals.approver',
        ]);

        $isStartup = $part === IpcApproval::STAGE_STARTUP;
        $reportDocument = IpcReportDocument::forKey($isStartup ? IpcReportDocument::KEY_STARTUP : IpcReportDocument::KEY_FILLING_PACKING_FG);

        $data = [
            ...$this->fillingPackingPayload($batch, $this->photoDataUris($batch, ['startup', IpcApproval::STAGE_PACKING]), IpcApproval::STAGE_PACKING),
            'part' => $part,
            'packingRevisionPhotoUris' => $isStartup ? [] : $this->packingRevisionPhotoUris($batch),
            'title' => $isStartup ? 'Form Start Up' : 'Form Packing',
            'docNumber' => $reportDocument?->doc_number ?? ($isStartup ? 'FR.QAC.182.03' : 'FR.QAC.193.00'),
            'docRevision' => $reportDocument?->revision ?? ($isStartup ? '03' : '0'),
        ];

        if ($isStartup) {
            $data['reportApproval'] = IpcApproval::forReport($batch->approvals, IpcApproval::STAGE_STARTUP);
            $data['verificationUrl'] = VerificationQrCode::url($batch, IpcApproval::STAGE_STARTUP);
            $data['verificationQr'] = VerificationQrCode::svg($batch, IpcApproval::STAGE_STARTUP);
        }

        return [
            'pdf.approval-filling-packing',
            $data,
            ($isStartup ? 'Startup-Form-' : 'Packing-Report-')."{$batch->no_batch}.pdf",
        ];
    }
}
