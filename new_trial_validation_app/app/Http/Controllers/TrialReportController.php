<?php

namespace App\Http\Controllers;

use App\Actions\Trials\CheckTrialCompleteness;
use App\Actions\Trials\RecordReportPrint;
use App\Models\LineConfigurationLane;
use App\Models\Trial;
use App\Models\TrialAttachmentFile;
use App\Models\TrialLineConfigurationReport;
use App\Models\TrialResult;
use App\Models\TrialReview;
use App\Models\TrialWeighing;
use App\Models\User;
use App\Models\ValidationParameter;
use App\Services\Pdf\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Port of legacy's per-trial Report Summary page (app/views/report.php,
 * public/index.php:740-793) and its print-log endpoint
 * (public/index.php:330-337) — the rich read/print view only. The
 * Submit-for-Review form that also lives on legacy's report.php was already
 * carved out into its own wizard Step 6 (see App\Http\Controllers\TrialReviewController),
 * so this controller doesn't duplicate it — show() only surfaces a
 * completeness note plus a link to that page when applicable.
 *
 * Also doubles as the single "detail" page an approver/reviewer lands on from
 * the Approval Queue / Review Queue (see ApprovalController::index() /
 * ReviewController::index()) — canApprove/pendingReviews below let this same
 * page carry the actual decision/review action inline, so acting on a trial
 * never requires bouncing back out to the list.
 */
class TrialReportController extends Controller
{
    public function show(Request $request, int $trial): Response
    {
        $trial = Trial::whereNull('deleted_at')->with(['product', 'approver'])->findOrFail($trial);

        Gate::authorize('view', $trial);

        $user = $request->user();

        $canApprove = $trial->progress_status === 'Ready for Approval' && Gate::allows('approve', $trial);

        $pendingReviews = collect();
        if ($trial->progress_status === 'In Review' && $user->isReviewer()) {
            $pendingReviews = TrialReview::query()
                ->where('trial_id', $trial->id)
                ->where('review_round', $trial->currentReviewRound())
                ->where('status', 'Pending')
                ->visibleToReviewer($user)
                ->get(['id', 'department']);
        }

        $editableReviews = collect();
        if ($trial->final_decision === null
            && in_array($trial->progress_status, ['In Review', 'Ready for Approval'], true)
            && $user->isReviewer()
        ) {
            $editableReviews = TrialReview::query()
                ->where('trial_id', $trial->id)
                ->where('review_round', $trial->currentReviewRound())
                ->where('status', 'Reviewed')
                ->where('edit_count', '<', TrialReview::MAX_EDITS)
                ->visibleToReviewer($user)
                ->get(['id', 'department', 'comment', 'edit_count']);
        }

        $core = $this->reportCore($trial);

        // Built via a plain foreach rather than ->groupBy()->map() — nested
        // Eloquent Collection generics there trip a known PHPStan/Larastan
        // false positive (TValue on Collection is not covariant), not a real
        // type error.
        $attachments = collect();
        foreach (TrialAttachmentFile::query()
            ->where('trial_id', $trial->id)
            ->whereNull('deleted_at')
            ->orderBy('category')
            ->orderBy('id')
            ->get()
            ->groupBy('category') as $category => $files) {
            $attachments[$category] = $files->map(fn (TrialAttachmentFile $file) => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'caption' => $file->caption,
                'url' => route('trials.attachments.show', [$trial->id, $file->id]),
            ])->values();
        }

        $reviewByDept = $trial->reviewStatusByDepartment();

        $lineConfigurationReport = TrialLineConfigurationReport::where('trial_id', $trial->id)
            ->where('is_locked', false)
            ->with(['approvedPieUser:id,name', 'checkedProdUser:id,name'])
            ->first();
        $lineConfigurationReportVersions = TrialLineConfigurationReport::where('trial_id', $trial->id)
            ->where('is_locked', true)
            ->orderByDesc('version')
            ->get(['id', 'version', 'locked_at', 'return_prod', 'return_reason']);
        $lineConfigurationApprovers = $this->approversForLane('approved_pie');
        // Checked(PROD) may only be assigned to someone on the team
        // currently configured for the 'checked_prod' lane (Phase 3 of the
        // RBAC/Team-master redesign — admin-editable via Lane Configuration,
        // no longer a hardcoded 'PROD' literal) — unlike Approved(PIE),
        // which has no required team configured by default and so stays
        // open to any active user.
        $lineConfigurationProdApprovers = $this->approversForLane('checked_prod');

        // Once Returned, the maker needs to actually see why when they
        // reopen the (now-unlocked) form — look at the version immediately
        // before this one, which is exactly the row Return locked.
        $lineConfigurationReturnNote = null;
        if ($lineConfigurationReport && $lineConfigurationReport->version > 1) {
            $previousVersion = TrialLineConfigurationReport::where('trial_id', $trial->id)
                ->where('version', $lineConfigurationReport->version - 1)
                ->where('is_locked', true)
                ->where('return_prod', true)
                ->first();

            if ($previousVersion) {
                $lineConfigurationReturnNote = [
                    'reason' => $previousVersion->return_reason,
                    'by' => $previousVersion->return_prod_by,
                    'at' => $previousVersion->return_prod_at?->toDateTimeString(),
                ];
            }
        }

        $approvalBlockedNote = null;
        if ($trial->progress_status === 'Ready for Approval' && ! $canApprove && $user->canApproveTrials()) {
            $approvalBlockedNote = 'Menunggu approval oleh '.($trial->pending_with ?: 'approver lain').', bukan giliran Anda.';
        }

        $reviewCompletedNote = null;
        if ($trial->progress_status === 'In Review' && $user->isReviewer() && $pendingReviews->isEmpty()) {
            $myDepartments = $user->reviewDepartmentsForUser();
            foreach ($reviewByDept as $dept => $entry) {
                if ($entry['status'] === 'Reviewed' && in_array(User::normalizeReviewDepartment($dept), $myDepartments, true)) {
                    $reviewCompletedNote = 'Anda sudah menyelesaikan review department Anda untuk trial ini.';
                    break;
                }
            }
        }

        return Inertia::render('trials/report', [
            'trial' => $trial,
            'results' => $core['results'],
            'weighingSections' => $core['weighingSections'],
            'attachments' => $attachments,
            'reviews' => $core['reviews'],
            'approvedByName' => $core['approvedByName'],
            'rejectedByName' => $core['rejectedByName'],
            'completeness' => (new CheckTrialCompleteness)($trial),
            'canEdit' => Gate::allows('update', $trial),
            'canApprove' => $canApprove,
            'pendingReviews' => $pendingReviews->map(fn (TrialReview $r) => [
                'id' => $r->id,
                'department' => $r->department,
            ])->values(),
            'editableReviews' => $editableReviews->map(fn (TrialReview $r) => [
                'id' => $r->id,
                'department' => $r->department,
                'comment' => $r->comment,
                'editsRemaining' => TrialReview::MAX_EDITS - $r->edit_count,
            ])->values(),
            'approvalBlockedNote' => $approvalBlockedNote,
            'reviewCompletedNote' => $reviewCompletedNote,
            'lineConfigurationReport' => $lineConfigurationReport,
            // Combines the general PROD-reviewer/Admin gate with two further
            // narrowings: (1) once a report already has a recorded drafter
            // (updated_by_user_id — whoever actually filled it in), only
            // that drafter may keep editing it, not just any PROD-team
            // member — being on the PROD team only grants the right to
            // start/claim a report nobody has drafted yet; (2) the
            // maker-checker lock — once submitted, even the drafter must
            // wait for a Return. Admin bypasses both.
            'canEditLineConfigurationReport' => Gate::allows('manage-line-configuration-report')
                && (
                    $user->isAdmin()
                    || (
                        (! $lineConfigurationReport || ! $lineConfigurationReport->blocksEditFor($user))
                        && (! $lineConfigurationReport || ! $lineConfigurationReport->isSubmittedForApproval())
                    )
                ),
            'lineConfigurationReportLocked' => $lineConfigurationReport?->isSubmittedForApproval() ?? false,
            'lineConfigurationReturnNote' => $lineConfigurationReturnNote,
            'lineConfigurationReportVersions' => $lineConfigurationReportVersions->map(fn (TrialLineConfigurationReport $v) => [
                'id' => $v->id,
                'version' => $v->version,
                'locked_at' => $v->locked_at?->toDateTimeString(),
                'return_prod' => $v->return_prod,
                'return_reason' => $v->return_reason,
            ])->values(),
            'lineConfigurationApprovers' => $lineConfigurationApprovers,
            'lineConfigurationProdApprovers' => $lineConfigurationProdApprovers,
            'lineConfigurationLanes' => LineConfigurationLane::labels(),
            'canApprovePieLineConfigurationReport' => $lineConfigurationReport ? Gate::allows('approvePie', $lineConfigurationReport) : false,
            'canCheckProdLineConfigurationReport' => $lineConfigurationReport ? Gate::allows('checkProd', $lineConfigurationReport) : false,
            'canReturnLineConfigurationReport' => $lineConfigurationReport ? Gate::allows('returnReport', $lineConfigurationReport) : false,
        ]);
    }

    public function logPrint(Request $request, int $trial, RecordReportPrint $action): JsonResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        Gate::authorize('view', $trial);

        $action($trial, $request->user());

        return response()->json(['ok' => true]);
    }

    /**
     * Server-rendered PDF (spatie/browsershot) of the same report page
     * show() renders — see ../../../CLAUDE.md "Print/PDF report approach".
     * Downloading the PDF is treated as "printing" the report, same as
     * legacy's window.print() flow: it fires the same report_printed audit
     * write logPrint() does, so no separate fetch-then-print call is needed
     * from the frontend anymore.
     */
    public function pdf(Request $request, int $trial, RecordReportPrint $action, PdfService $pdf): HttpResponse
    {
        $trial = Trial::whereNull('deleted_at')->with(['product', 'approver'])->findOrFail($trial);

        Gate::authorize('view', $trial);

        $action($trial, $request->user());

        $core = $this->reportCore($trial);

        // See the matching comment in show() above: built via foreach to
        // avoid a PHPStan/Larastan false positive on nested Collection
        // generics from ->groupBy()->map().
        $attachments = collect();
        foreach (TrialAttachmentFile::query()
            ->where('trial_id', $trial->id)
            ->whereNull('deleted_at')
            ->orderBy('category')
            ->orderBy('id')
            ->get()
            ->groupBy('category') as $category => $files) {
            $attachments[$category] = $files->map(fn (TrialAttachmentFile $file) => [
                'file_name' => $file->file_name,
                'caption' => $file->caption,
                'src' => $this->attachmentDataUri($file),
            ])->values();
        }

        return $pdf->fromView('pdf.trial-report', [
            'title' => 'Report — '.$trial->trial_code,
            'trial' => $trial,
            'results' => $core['results'],
            'weighingSections' => $core['weighingSections'],
            'attachments' => $attachments,
            'reviews' => $core['reviews'],
            'approvedByName' => $core['approvedByName'],
            'rejectedByName' => $core['rejectedByName'],
        ], "Report-{$trial->trial_code}.pdf");
    }

    /**
     * Server-side Excel export (PhpSpreadsheet) of the same report data
     * show()/pdf() render — one sheet each for the header info, validation
     * parameters, weighing stats, department reviews, and the final
     * Manager QAC decision. Counts as "printing" the same way the PDF
     * download does, so it fires the same report_printed audit write.
     */
    public function excel(Request $request, int $trial, RecordReportPrint $action): StreamedResponse
    {
        $trial = Trial::whereNull('deleted_at')->with(['product', 'approver'])->findOrFail($trial);

        Gate::authorize('view', $trial);

        $action($trial, $request->user());

        $core = $this->reportCore($trial);
        $spreadsheet = $this->buildExcel($trial, $core);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "Report-{$trial->trial_code}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $core
     */
    private function buildExcel(Trial $trial, array $core): Spreadsheet
    {
        $displayDecision = in_array($trial->progress_status, ['Approved', 'Rejected'], true)
            ? ($trial->final_decision ?? $trial->progress_status)
            : $trial->progress_status;

        $spreadsheet = new Spreadsheet;

        $this->writeKeyValueSheet($spreadsheet->getActiveSheet(), 'Info Trial', 'Informasi Trial', [
            ['Trial ID', $trial->trial_code],
            ['Product Name', $trial->product_name],
            ['FG Code', $trial->finish_good_code],
            ['Validation Category', $trial->validation_category],
            ['Validation Scope', implode(', ', $trial->validation_scope ?? [])],
            ['Product Type', $trial->product_type],
            ['Validation Date', $trial->validation_date],
            ['Risk Level', $trial->risk_level],
            ['Machine Used', implode(', ', $trial->machine_used ?? [])],
            ['Batch Number', $trial->batch_number],
            ['Bulk Code', $trial->bulk_code],
            ['Estimate Qty', $trial->estimate_qty],
            ['Support Team', $trial->support_team],
            ['Initiated Person/Team', $trial->initiated_person_team],
            ['Reason', $trial->reason],
            ['B.O.M', $trial->bom],
            ['Created By', $trial->created_by],
            ['Status', $trial->progress_status],
            ['Pending With', $trial->pending_with],
            ['Selected Approver', $trial->approver ? ($trial->approver->name ?: $trial->approver->email) : null],
            ['Revision No', $trial->revision_no],
            ['Approval Status', $displayDecision],
        ]);

        $this->writeTableSheet($spreadsheet->createSheet(), 'Validasi', [
            'Parameter', 'Spesifikasi', 'Decision', 'Hasil', 'Catatan',
        ], $core['results']->map(fn (array $r) => [
            $r['parameter_name'], $r['specification'], $r['decision'], $r['result_value'], $r['remark'],
        ])->all());

        $this->writeTableSheet($spreadsheet->createSheet(), 'Weighing', [
            'Section', 'Total Sample', 'Rata-rata', 'Minimum', 'Maksimum',
        ], $core['weighingSections']->map(fn (array $section) => [
            $section['section'],
            $section['stats']['count'],
            $section['stats']['avg'] !== null ? round($section['stats']['avg'], 2) : null,
            $section['stats']['min'],
            $section['stats']['max'],
        ])->all());

        $this->writeTableSheet($spreadsheet->createSheet(), 'Review Department', [
            'Round', 'Department', 'Status', 'Reviewer', 'Direview Pada', 'Komentar',
        ], $core['reviews']->map(fn (array $r) => [
            $r['review_round'], $r['department'], $r['status'], $r['reviewer_name'], $r['reviewed_at'], $r['comment'],
        ])->all());

        $managerDecision = $trial->final_decision ?? $trial->progress_status;
        $decisionBy = $managerDecision === 'Approved' ? $core['approvedByName'] : $core['rejectedByName'];
        $decisionAt = $managerDecision === 'Approved' ? $trial->approved_at : $trial->rejected_at;

        $this->writeKeyValueSheet($spreadsheet->createSheet(), 'Keputusan', 'Keputusan Manager QAC', [
            ['Decision', $managerDecision],
            ['Status', $trial->progress_status],
            ['Diputuskan Oleh', $decisionBy],
            ['Diputuskan Pada', $decisionAt],
            ['Komentar', $trial->approval_comment],
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  list<array{0: string, 1: mixed}>  $rows
     */
    private function writeKeyValueSheet(Worksheet $sheet, string $sheetTitle, string $heading, array $rows): void
    {
        $sheet->setTitle($sheetTitle);

        $sheet->setCellValue('A1', $heading);
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $rowIndex = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$rowIndex}", $label);
            $sheet->setCellValue("B{$rowIndex}", $this->cellValue($value));
            $sheet->getStyle("A{$rowIndex}")->getFont()->setBold(true);
            $rowIndex++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(50);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function writeTableSheet(Worksheet $sheet, string $sheetTitle, array $headers, array $rows): void
    {
        $sheet->setTitle($sheetTitle);

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $header);
        }
        $headerRange = "A1:{$lastColumn}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EA1D22');

        $rowIndex = 2;
        foreach ($rows as $row) {
            foreach ($row as $i => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$rowIndex, $this->cellValue($value));
            }
            $rowIndex++;
        }

        if ($rows === []) {
            $sheet->setCellValue('A2', 'Tidak ada data.');
        }

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    private function cellValue(mixed $value): string|int|float
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function reportCore(Trial $trial): array
    {
        // Scoped to the trial's current product_type so a trials_results row
        // left over from before product_type was changed (rows are never
        // deleted on change) doesn't surface as a validation result for a
        // product it no longer belongs to.
        $currentParameterIds = ValidationParameter::query()
            ->where('product_type', $trial->product_type)
            ->pluck('id');

        $results = TrialResult::query()
            ->where('trial_id', $trial->id)
            ->whereIn('parameter_id', $currentParameterIds)
            ->with('parameter')
            ->get()
            ->sortBy(fn (TrialResult $r) => sprintf('%010d-%010d', $r->parameter->sort_order, $r->parameter_id))
            ->values();

        $weighings = TrialWeighing::query()
            ->where('trial_id', $trial->id)
            ->orderBy('item_no')
            ->get()
            ->groupBy('section');

        $weighingSections = collect(['Packaging', 'Filling'])->map(function (string $section) use ($weighings) {
            $items = $weighings->get($section, collect());

            return [
                'section' => $section,
                'stats' => TrialWeighing::statsForSection($items),
            ];
        });

        $reviewByDept = $trial->reviewStatusByDepartment();

        return [
            'results' => $results->map(fn (TrialResult $r) => [
                'parameter_name' => $r->parameter->parameter_name,
                'specification' => $r->parameter->specification,
                'decision' => $r->decision,
                'result_value' => $r->result_value,
                'remark' => $r->remark,
            ])->values(),
            'weighingSections' => $weighingSections,
            'reviews' => collect($reviewByDept)->map(fn (array $entry, string $dept) => [
                'department' => $dept,
                'review_round' => $trial->currentReviewRound(),
                'status' => $entry['status'],
                'reviewer_name' => $entry['review']?->reviewer_name ? User::displayName($entry['review']->reviewer_name) : null,
                'reviewed_at' => $entry['review']?->reviewed_at?->toDateTimeString(),
                'comment' => $entry['review']?->comment,
            ])->values(),
            'approvedByName' => $trial->approved_by ? User::displayName($trial->approved_by) : null,
            'rejectedByName' => $trial->rejected_by ? User::displayName($trial->rejected_by) : null,
        ];
    }

    /**
     * Browsershot renders a standalone HTML string with no HTTP context, so
     * a relative/authenticated route URL (what show()'s attachments use)
     * would never load — embed the file directly as a data: URI instead.
     */
    private function attachmentDataUri(TrialAttachmentFile $file): string
    {
        $disk = Storage::disk('legacy_uploads');
        $path = $file->trial_id.'/'.$file->file_name;

        if (! $disk->exists($path)) {
            return '';
        }

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    /**
     * Active users eligible to be assigned to a Line Configuration Report
     * sign-off stage — constrained to the team currently configured for
     * that lane (Phase 3 of the RBAC/Team-master redesign — see
     * LineConfigurationLane), or every active user if the lane has no
     * required team configured.
     *
     * @return array<int, array{id: int, label: string}>
     */
    private function approversForLane(string $stageKey): array
    {
        return LineConfigurationLane::constrainToStage(
            User::query()->where('is_active', 1)->whereNull('deleted_at'),
            $stageKey,
        )
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'label' => trim((string) ($u->name ?: $u->email))])
            ->values()
            ->all();
    }
}
