<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterOption;
use App\Models\Trial;
use App\Models\User;
use App\Services\Pdf\PdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Port of legacy's /report hub + 5 report list pages
 * (public/index.php:243-329, app/views/report_*.php). No extra Gate beyond
 * the standard auth+verified middleware — legacy's /report/* routes have no
 * role check either, every method here just applies Trial::visibleTo() for
 * the same row-level scoping every other list page already uses.
 *
 * Each list also has a PDF twin (spatie/browsershot — see ../../../CLAUDE.md
 * "Print/PDF report approach"), which exports every matching row rather than
 * just the current paginated page, since a "download PDF" action reads as a
 * full export, not a screenshot of whatever page happens to be open.
 */
class ReportController extends Controller
{
    /**
     * ->through() on a LengthAwarePaginator<int, Trial> confuses PHPStan's
     * generic resolution (an "unresolvable type" error with no useful
     * suggestion) — building the paginated array by hand instead sidesteps
     * that entirely.
     *
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, Trial>  $paginator
     * @param  callable(Trial): TItem  $map
     * @return array{data: array<int, TItem>, current_page: int, last_page: int, per_page: int, total: int}
     */
    private function paginatedArray(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => $paginator->getCollection()->map($map)->values()->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('reports/index');
    }

    public function approved(Request $request): Response
    {
        $items = $this->approvedQuery($request)->paginate(10)->withQueryString();

        return Inertia::render('reports/approved', [
            'items' => $this->paginatedArray($items, $this->approvedRow(...)),
        ]);
    }

    public function approvedPdf(Request $request, PdfService $pdf): HttpResponse
    {
        $items = $this->approvedQuery($request)->get()->map($this->approvedRow(...));

        return $pdf->fromView('pdf.approved', [
            'title' => 'Approved Report',
            'items' => $items,
        ], 'Approved-Report.pdf');
    }

    /**
     * @return Builder<Trial>
     */
    private function approvedQuery(Request $request): Builder
    {
        return Trial::query()
            ->visibleTo($request->user(), 'approved')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function approvedRow(Trial $trial): array
    {
        return [
            'id' => $trial->id,
            'trial_code' => $trial->trial_code,
            'product_name' => $trial->product_name,
            'finish_good_code' => $trial->finish_good_code,
            'product_type' => $trial->product_type,
            'approved_at' => $trial->approved_at?->toDateTimeString(),
            'approved_by' => $trial->approved_by ? User::displayName($trial->approved_by) : null,
        ];
    }

    public function rejected(Request $request): Response
    {
        $items = $this->rejectedQuery($request)->paginate(10)->withQueryString();

        return Inertia::render('reports/rejected', [
            'items' => $this->paginatedArray($items, $this->rejectedRow(...)),
        ]);
    }

    public function rejectedPdf(Request $request, PdfService $pdf): HttpResponse
    {
        $items = $this->rejectedQuery($request)->get()->map($this->rejectedRow(...));

        return $pdf->fromView('pdf.rejected', [
            'title' => 'Rejected Report',
            'items' => $items,
        ], 'Rejected-Report.pdf');
    }

    /**
     * @return Builder<Trial>
     */
    private function rejectedQuery(Request $request): Builder
    {
        return Trial::query()
            ->visibleTo($request->user(), 'rejected')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function rejectedRow(Trial $trial): array
    {
        return [
            'id' => $trial->id,
            'trial_code' => $trial->trial_code,
            'product_name' => $trial->product_name,
            'finish_good_code' => $trial->finish_good_code,
            'product_type' => $trial->product_type,
            'rejected_at' => $trial->rejected_at?->toDateTimeString(),
            'rejected_by' => $trial->rejected_by ? User::displayName($trial->rejected_by) : null,
            'approval_comment' => $trial->approval_comment,
        ];
    }

    public function trialSummary(Request $request): Response
    {
        $filters = $this->trialSummaryFilters($request);

        $items = $this->trialSummaryQuery($request, $filters)->paginate(10)->withQueryString();

        return Inertia::render('reports/trial-summary', [
            'items' => $items,
            'filters' => $filters,
            ...$this->trialSummaryOptions(),
        ]);
    }

    public function trialSummaryPdf(Request $request, PdfService $pdf): HttpResponse
    {
        $filters = $this->trialSummaryFilters($request);

        $items = $this->trialSummaryQuery($request, $filters)->get();

        return $pdf->fromView('pdf.trial-summary', [
            'title' => 'Trial Summary Report',
            'items' => $items,
        ], 'Trial-Summary-Report.pdf');
    }

    /**
     * @return array<string, string>
     */
    private function trialSummaryFilters(Request $request): array
    {
        return [
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'status' => trim((string) $request->query('status', '')),
            'product_type' => trim((string) $request->query('product_type', '')),
            'validation_scope' => trim((string) $request->query('validation_scope', '')),
            'machine_used' => trim((string) $request->query('machine_used', '')),
            'product_name' => trim((string) $request->query('product_name', '')),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Trial>
     */
    private function trialSummaryQuery(Request $request, array $filters): Builder
    {
        return Trial::query()
            ->visibleTo($request->user())
            ->trialSummaryFilters($filters)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{productTypes: Collection<int, string>, validationScopes: Collection<int, string>, machines: Collection<int, string>}
     */
    private function trialSummaryOptions(): array
    {
        $option = fn (string $type) => MasterOption::query()
            ->where('type', $type)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        return [
            'productTypes' => $option('product_type'),
            'validationScopes' => $option('validation_scope'),
            'machines' => $option('machine_used'),
        ];
    }

    public function departmentReview(Request $request): Response
    {
        $items = $this->departmentReviewQuery($request)->paginate(10)->withQueryString();

        return Inertia::render('reports/department-review', [
            'items' => $this->paginatedArray($items, $this->departmentReviewRow(...)),
            'reviewerDepartments' => User::reviewerDepartmentCodes(),
        ]);
    }

    public function departmentReviewPdf(Request $request, PdfService $pdf): HttpResponse
    {
        $items = $this->departmentReviewQuery($request)->get()->map($this->departmentReviewRow(...));

        return $pdf->fromView('pdf.department-review', [
            'title' => 'Department Review Report',
            'items' => $items,
            'reviewerDepartments' => User::reviewerDepartmentCodes(),
        ], 'Department-Review-Report.pdf');
    }

    /**
     * @return Builder<Trial>
     */
    private function departmentReviewQuery(Request $request): Builder
    {
        return Trial::query()
            ->visibleTo($request->user())
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function departmentReviewRow(Trial $trial): array
    {
        $statuses = array_map(fn (array $entry) => $entry['status'], $trial->reviewStatusByDepartment());
        $required = array_filter($statuses, fn (string $status) => $status !== 'N/A');

        $reviewStatus = 'N/A';
        if ($required) {
            $reviewStatus = in_array('Pending', $required, true) ? 'Pending' : 'Reviewed';
        }

        return [
            'id' => $trial->id,
            'trial_code' => $trial->trial_code,
            'product_name' => $trial->product_name,
            'pending_with' => $trial->pending_with,
            'departments' => $statuses,
            'review_status' => $reviewStatus,
        ];
    }

    public function auditPrintLog(): Response
    {
        $items = $this->auditPrintLogQuery()->paginate(10)->withQueryString();

        return Inertia::render('reports/audit-print-log', [
            'items' => $items,
        ]);
    }

    public function auditPrintLogPdf(PdfService $pdf): HttpResponse
    {
        $items = $this->auditPrintLogQuery()->get()->map(fn (AuditLog $log) => [
            'trial_code' => $log->trial?->trial_code,
            'user_email' => $log->user_email,
            'created_at' => $log->created_at?->toDateTimeString(),
            'report_type' => $log->new_data['report_type'] ?? 'Report',
        ]);

        return $pdf->fromView('pdf.audit-print-log', [
            'title' => 'Audit Print Log',
            'items' => $items,
        ], 'Audit-Print-Log.pdf');
    }

    /**
     * @return Builder<AuditLog>
     */
    private function auditPrintLogQuery(): Builder
    {
        return AuditLog::query()
            ->where('action', 'report_printed')
            ->with('trial:id,trial_code')
            ->orderByDesc('created_at');
    }
}
