<?php

namespace App\Http\Controllers;

use App\Actions\Prints\LogPrint;
use App\Http\Controllers\Concerns\BuildsIpcReportPayloads;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Services\Pdf\PdfService;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports legacy's gallery_printer -> {StartReport_View, FIllingPackingReport_View,
 * FinishedReport_View} -> gallery_printer flow — the final MAIN["PRINT"] stage, reached only
 * once all three Approval stages are Approved (batch.current_stage === 'print'). Same
 * read-only-report shape as ApprovalController's detail pages (shares BuildsIpcReportPayloads so
 * the two can't drift apart), minus the decision form: legacy's *_View screens are pure
 * read+print, no approve/reject action of their own.
 *
 * pdf() doubles as the actual "print" action (confirmed with the user 2026-09-04): opening the
 * PDF logs an IpcPrintLog row every time, same bare-flag-flip spirit as legacy's real Print()
 * call, just with real history instead of a single Y flag. Once every stage has been printed at
 * least once, the batch auto-advances to 'completed'.
 */
class PrintController extends Controller
{
    use BuildsIpcReportPayloads;

    public function edit(IpcBatch $batch): Response
    {
        $this->guardPrintable($batch);

        $batch->load(['masterProduct', 'masterLine', 'printLogs.printedBy']);

        return Inertia::render('print/index', [
            'batch' => $batch,
            'stages' => $this->stagesSummary($batch),
        ]);
    }

    public function startup(IpcBatch $batch): Response
    {
        $this->guardPrintable($batch);

        $batch->load([
            'masterProduct',
            'masterLine',
            'startupCheck.user',
            'startupInspection.items',
            'startupInspection.samples',
            'startupInspection.testResults.testType',
        ]);

        return Inertia::render('print/startup', [
            'batch' => $batch,
            'printInfo' => $this->printInfo($batch, IpcApproval::STAGE_STARTUP),
            ...$this->startupPayload($batch, $this->photoUrls($batch, ['startup'])),
        ]);
    }

    public function fillingPacking(IpcBatch $batch): Response
    {
        $this->guardPrintable($batch);

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
        ]);

        return Inertia::render('print/filling-packing', [
            'batch' => $batch,
            'printInfo' => $this->printInfo($batch, IpcApproval::STAGE_FILLING_PACKING),
            ...$this->fillingPackingPayload($batch, $this->photoUrls($batch, ['filling', 'packing'])),
        ]);
    }

    public function finished(IpcBatch $batch): Response
    {
        $this->guardPrintable($batch);

        $batch->load([
            'masterProduct',
            'masterLine',
            'finishedCheck.user',
            'finishedCheck.samples',
            'finishedCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'finishedCheck.revisions.user',
            'finishedCheck.revisions.samples',
        ]);

        return Inertia::render('print/finished', [
            'batch' => $batch,
            'printInfo' => $this->printInfo($batch, IpcApproval::STAGE_FINISHED),
            ...$this->finishedPayload($batch, $this->photoUrls($batch, ['finished'])),
        ]);
    }

    /**
     * The real "print" action, reserved for the header's "Cetak" button — every view is logged to
     * ipc_print_logs, which is what the Print overview's "Dicetak Nx"/"Terakhir oleh ..." history
     * and the batch's print->completed auto-advance are both driven by. Kept as a distinct route
     * from preview() (added 2026-09-08, direct user request) precisely so a QC/audit user opening
     * the document just to look at it — expected to be the common case, per the user — doesn't
     * inflate that history with views that were never an actual physical print.
     */
    public function pdf(IpcBatch $batch, string $stage, PdfService $pdf, LogPrint $logPrint): HttpResponse
    {
        $this->guardPrintable($batch);

        $batch->load(['masterProduct', 'masterLine']);

        [$view, $data, $filename] = $this->resolveReportPdf($batch, $stage);

        $logPrint->handle($batch, request()->user(), $stage);

        return $pdf->fromView($view, ['batch' => $batch, ...$data], $filename);
    }

    /**
     * Same PDF, same Blade view, deliberately not logged — for "just looking at the document"
     * (e.g. an audit review) as opposed to an actual print. See pdf()'s doc comment above for why
     * these are two separate actions/routes rather than one.
     */
    public function preview(IpcBatch $batch, string $stage, PdfService $pdf): HttpResponse
    {
        $this->guardPrintable($batch);

        $batch->load(['masterProduct', 'masterLine']);

        [$view, $data, $filename] = $this->resolveReportPdf($batch, $stage);

        return $pdf->fromView($view, ['batch' => $batch, ...$data], $filename);
    }

    /**
     * @return array<int, array{stage: string, label: string, printCount: int, lastPrintedAt: ?string, lastPrintedBy: ?string}>
     */
    private function stagesSummary(IpcBatch $batch): array
    {
        $logsByStage = $batch->printLogs->groupBy('stage');

        return collect(IpcApproval::STAGES)->map(fn (string $stage) => [
            'stage' => $stage,
            'label' => IpcApproval::STAGE_LABELS[$stage],
            ...$this->summarizeLogs($logsByStage->get($stage, collect())),
        ])->values()->all();
    }

    /**
     * @return array{printCount: int, lastPrintedAt: ?string, lastPrintedBy: ?string}
     */
    private function printInfo(IpcBatch $batch, string $stage): array
    {
        return [
            'stage' => $stage,
            'label' => IpcApproval::STAGE_LABELS[$stage],
            ...$this->summarizeLogs($batch->printLogs()->where('stage', $stage)->with('printedBy')->latest('printed_at')->get()),
        ];
    }

    private function summarizeLogs($logs): array
    {
        $latest = $logs->sortByDesc('printed_at')->first();

        return [
            'printCount' => $logs->count(),
            'lastPrintedAt' => $latest?->printed_at,
            'lastPrintedBy' => $latest?->printedBy?->name,
        ];
    }

    private function guardPrintable(IpcBatch $batch): void
    {
        abort_unless(
            in_array($batch->current_stage, [IpcBatch::STAGE_PRINT, IpcBatch::STAGE_COMPLETED], true),
            403,
            'Batch ini belum masuk tahap Print — semua tahap Approval harus Approved terlebih dahulu.'
        );
    }
}
