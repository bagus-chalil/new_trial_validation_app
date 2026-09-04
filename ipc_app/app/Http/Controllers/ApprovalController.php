<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\SaveApproval;
use App\Http\Requests\SaveApprovalRequest;
use App\Models\FinishedCheckSample;
use App\Models\IpcApproval;
use App\Models\IpcAttachment;
use App\Models\IpcBatch;
use App\Models\MasterTestType;
use App\Models\PackingCheck;
use App\Models\StartupCheck;
use App\Models\StartupInspectionItem;
use App\Services\Pdf\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports legacy's gallery_TEST -> {StartReport_Approval, FIllingPackingReport_Approval,
 * FinishedReport_Approval} -> gallery_TEST flow. Originally a single long page rendering all
 * three report sections at once (see git history) — split 2026-09-04 into an overview
 * (edit()) plus one detail route per stage, per direct user feedback that seeing all three
 * forms exposed simultaneously was overwhelming/confusing. Each detail route also has a
 * Browsershot-rendered print-preview twin (print()) styled to closely match the legacy
 * Excel-style report screenshots the user shared, mirroring the pattern already established in
 * ../../../new_trial_validation_app/app/Services/Pdf.
 */
class ApprovalController extends Controller
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

    public function edit(IpcBatch $batch): Response
    {
        $this->guardFinished($batch);

        $batch->load(['masterProduct', 'masterLine', 'startupCheck', 'fillingCheck', 'packingCheck', 'finishedCheck', 'approvals.approver']);

        return Inertia::render('approval/index', [
            'batch' => $batch,
            'stages' => $this->stagesSummary($batch),
        ]);
    }

    public function startup(IpcBatch $batch): Response
    {
        $this->guardFinished($batch);

        $batch->load([
            'masterProduct',
            'masterLine',
            'startupCheck.user',
            'startupInspection.items',
            'startupInspection.samples',
            'startupInspection.testResults.testType',
            'approvals',
        ]);

        return Inertia::render('approval/startup', [
            'batch' => $batch,
            'stage' => $this->stageInfo($batch, IpcApproval::STAGE_STARTUP),
            'decisions' => IpcApproval::DECISIONS,
            ...$this->startupPayload($batch, $this->photoUrls($batch, ['startup'])),
        ]);
    }

    public function fillingPacking(IpcBatch $batch): Response
    {
        $this->guardFinished($batch);

        $batch->load([
            'masterProduct',
            'masterLine',
            'startupCheck',
            'fillingCheck.user',
            'fillingCheck.samples',
            'fillingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'fillingCheck.revisions.user',
            'packingCheck.user',
            'packingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'packingCheck.revisions.user',
            'approvals',
        ]);

        return Inertia::render('approval/filling-packing', [
            'batch' => $batch,
            'stage' => $this->stageInfo($batch, IpcApproval::STAGE_FILLING_PACKING),
            'decisions' => IpcApproval::DECISIONS,
            ...$this->fillingPackingPayload($batch, $this->photoUrls($batch, ['filling', 'packing'])),
        ]);
    }

    public function finished(IpcBatch $batch): Response
    {
        $this->guardFinished($batch);

        $batch->load([
            'masterProduct',
            'masterLine',
            'finishedCheck.user',
            'finishedCheck.samples',
            'finishedCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'finishedCheck.revisions.user',
            'finishedCheck.revisions.samples',
            'approvals',
        ]);

        return Inertia::render('approval/finished', [
            'batch' => $batch,
            'stage' => $this->stageInfo($batch, IpcApproval::STAGE_FINISHED),
            'decisions' => IpcApproval::DECISIONS,
            ...$this->finishedPayload($batch, $this->photoUrls($batch, ['finished'])),
        ]);
    }

    public function update(SaveApprovalRequest $request, IpcBatch $batch, string $stage, SaveApproval $action): RedirectResponse
    {
        $this->guardFinished($batch);
        abort_unless(IpcApproval::stageReady($batch, $stage), 403, 'Tahap ini belum selesai, belum bisa di-approve.');

        $action->handle($batch, $request->user(), $stage, $request->validated());

        $route = match ($stage) {
            IpcApproval::STAGE_STARTUP => 'approval.startup',
            IpcApproval::STAGE_FILLING_PACKING => 'approval.filling-packing',
            IpcApproval::STAGE_FINISHED => 'approval.finished',
            default => 'approval.edit',
        };

        return redirect()->route($route, $batch)->with('success', 'Keputusan approval tersimpan.');
    }

    /**
     * Server-rendered PDF (spatie/browsershot) of one report section, styled to mirror the
     * legacy Excel-style form the user shared screenshots of.
     */
    public function print(IpcBatch $batch, string $stage, PdfService $pdf): HttpResponse
    {
        $this->guardFinished($batch);

        $batch->load(['masterProduct', 'masterLine']);

        [$view, $data, $filename] = match ($stage) {
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
                        'packingCheck.user',
                        'packingCheck.revisions' => fn ($query) => $query->latest('revision_no'),
                        'packingCheck.revisions.user',
                    ]);

                    return $this->fillingPackingPayload($batch, $this->photoDataUris($batch, ['filling', 'packing']));
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

        return $pdf->fromView($view, ['batch' => $batch, ...$data], $filename);
    }

    /**
     * @return array<int, array{stage: string, label: string, ready: bool, approval: ?IpcApproval}>
     */
    private function stagesSummary(IpcBatch $batch): array
    {
        $approvals = $batch->approvals->keyBy('stage');

        return collect(IpcApproval::STAGES)->map(fn (string $stage) => [
            'stage' => $stage,
            'label' => IpcApproval::STAGE_LABELS[$stage],
            'ready' => IpcApproval::stageReady($batch, $stage),
            'approval' => $approvals->get($stage),
        ])->values()->all();
    }

    /**
     * @return array{stage: string, label: string, ready: bool, approval: ?IpcApproval}
     */
    private function stageInfo(IpcBatch $batch, string $stage): array
    {
        return [
            'stage' => $stage,
            'label' => IpcApproval::STAGE_LABELS[$stage],
            'ready' => IpcApproval::stageReady($batch, $stage),
            'approval' => $batch->approvals->firstWhere('stage', $stage),
        ];
    }

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

    private function guardFinished(IpcBatch $batch): void
    {
        abort_unless($batch->finishedCheck?->completed_at, 403, 'Finished Check untuk batch ini belum selesai — batch belum masuk tahap Approval.');
    }
}
