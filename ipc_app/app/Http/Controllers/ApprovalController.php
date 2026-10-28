<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\SaveApproval;
use App\Http\Controllers\Concerns\BuildsIpcReportPayloads;
use App\Http\Requests\SaveApprovalRequest;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcReportDocument;
use App\Services\Pdf\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
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
    use BuildsIpcReportPayloads;

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
            'approvals.approver',
            'approvals.revisions' => fn ($query) => $query->latest('revision_no'),
            'approvals.revisions.user',
        ]);

        return Inertia::render('approval/startup', [
            'batch' => $batch,
            'stage' => $this->stageInfo($batch, IpcApproval::STAGE_STARTUP),
            'decisions' => IpcApproval::DECISIONS,
            ...$this->startupPayload($batch, $this->photoUrls($batch, ['startup'])),
        ]);
    }

    public function filling(IpcBatch $batch): Response
    {
        return $this->fillingOrPacking($batch, IpcApproval::STAGE_FILLING);
    }

    public function packing(IpcBatch $batch): Response
    {
        return $this->fillingOrPacking($batch, IpcApproval::STAGE_PACKING);
    }

    /** Filling and Packing are separate reports/approvals sharing one page component (`side`). */
    private function fillingOrPacking(IpcBatch $batch, string $side): Response
    {
        $this->guardFinished($batch);
        abort_unless(in_array($side, $batch->activeStages(), true), 404, 'Tahap ini tidak digunakan pada alur batch ini.');

        $batch->load([
            'masterProduct',
            'masterLine',
            'startupCheck',
            ...$this->fillingPackingRelations($side),
            'approvals.approver',
            'approvals.revisions' => fn ($query) => $query->latest('revision_no'),
            'approvals.revisions.user',
        ]);

        return Inertia::render('approval/filling-packing', [
            'batch' => $batch,
            'stage' => $this->stageInfo($batch, $side),
            'decisions' => IpcApproval::DECISIONS,
            ...$this->fillingPackingPayload($batch, $this->photoUrls($batch, ['startup', $side]), $side),
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
            'approvals.approver',
            'approvals.revisions' => fn ($query) => $query->latest('revision_no'),
            'approvals.revisions.user',
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
            IpcApproval::STAGE_FILLING => 'approval.filling',
            IpcApproval::STAGE_PACKING => 'approval.packing',
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

        [$view, $data, $filename] = $this->resolveReportPdf($batch, $stage);

        // Finished Good (FR.QAC.193.00 page 2) is a landscape paper form.
        return $pdf->fromView($view, ['batch' => $batch, ...$data], $filename, $stage === IpcApproval::STAGE_FINISHED, $stage === IpcReportDocument::KEY_FILLING_PACKING_FG);
    }

    /**
     * @return array<int, array{stage: string, label: string, ready: bool, approval: ?IpcApproval}>
     */
    private function stagesSummary(IpcBatch $batch): array
    {
        $approvals = $batch->approvals->keyBy('stage');

        // Startup is deliberately hidden from the Approval overview per direct user request
        // (2026-09-23) — its own detail/decision page (`approval.startup`) is left in place and
        // still reachable directly, this only removes its card from this list. Print's own
        // overview (PrintController::stagesSummary(), a separate method) is untouched — the user
        // scoped this to Approval only.
        $stages = collect(IpcApproval::requiredStagesFor($batch))
            ->map(fn (string $stage) => [
                'stage' => $stage,
                'label' => IpcApproval::STAGE_LABELS[$stage],
                'ready' => IpcApproval::stageReady($batch, $stage),
                'approval' => $approvals->get($stage),
            ]);

        // Flow Lengkap also has the combined FR.QAC.193.00 document (Filling + Packing page plus
        // the landscape Finished Good page). It has no decision of its own — each stage above is
        // still approved separately — so this is a PDF-only card, same as on the Print hub.
        if ($batch->flow_type === IpcBatch::FLOW_FULL) {
            $stages->push([
                'stage' => IpcReportDocument::KEY_FILLING_PACKING_FG,
                'label' => 'Filling & Packing (Gabungan)',
                'ready' => true,
                'approval' => null,
            ]);
        }

        return $stages->values()->all();
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

    private function guardFinished(IpcBatch $batch): void
    {
        abort_unless($batch->isReadyForApproval(), 403, 'Tahap pemeriksaan untuk batch ini belum selesai — batch belum masuk tahap Approval.');
    }
}
