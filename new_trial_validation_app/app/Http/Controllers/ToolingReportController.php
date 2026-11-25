<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tooling;
use App\Services\Pdf\PdfService;
use App\Services\Projects\ToolingWorkflow;
use App\Support\ToolingOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Check Tooling Report (FR.PRNI.002.00): the finished documents (Approved or
 * Ditolak) with the Need Invest / No Invest / project filters, the CSV export
 * and the printable PDF / Word form. Requests still moving through the
 * workflow stay in the queue page (ToolingController::index).
 */
class ToolingReportController extends Controller
{
    private const FILTERS = ['all', 'need_invest', 'no_invest', 'project', 'rejected'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Tooling::class);

        $q = trim((string) $request->query('q', ''));
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? (string) $request->query('filter') : 'all';

        $all = Tooling::query()
            ->visibleTo($request->user())
            ->whereNull('deleted_at')
            ->with(['approvals', 'project'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $queue = $all->filter(fn (Tooling $t) => ! ToolingWorkflow::isFinal($t));
        $done = $all->filter(fn (Tooling $t) => ToolingWorkflow::isFinal($t))->values();

        $approved = $done->where('status', ToolingOptions::STATUS_APPROVED);
        $needInvest = $approved->filter(fn (Tooling $t) => $this->isNeedInvestPending($t));
        $noInvest = $approved->where('need_invest', 'NO');
        $withProject = $done->filter(fn (Tooling $t) => $this->liveProject($t) !== null);
        $rejected = $done->where('status', ToolingOptions::STATUS_REJECTED);

        $rows = match ($filter) {
            'need_invest' => $needInvest,
            'no_invest' => $noInvest,
            'project' => $withProject,
            'rejected' => $rejected,
            default => $done,
        };

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(fn (Tooling $t) => str_contains(mb_strtolower(implode(' ', [
                $t->npd_no, $t->client_code, $t->product_name, $t->marketing, $t->packdev,
                $t->packaging_type, (string) $t->bom_primary, $t->recommended_machine, $t->submitted_by_name, $t->pi_owner_name,
            ])), $needle));
        }

        return Inertia::render('toolings/report', [
            'items' => $rows->take(300)->map(fn (Tooling $t) => $this->row($t))->values(),
            'kpi' => [
                'queue' => $queue->count(),
                'queue_pie' => $queue->where('status', ToolingOptions::STATUS_PIE_CHECK)->count(),
                'queue_production' => $queue->where('status', ToolingOptions::STATUS_PRODUCTION)->count(),
                'done' => $approved->count(),
                'need_invest' => $all->where('need_invest', 'YES')->count(),
                'need_invest_ready' => $needInvest->count(),
                'with_project' => $withProject->count(),
                'no_invest' => $noInvest->count(),
            ],
            'counts' => [
                'all' => $done->count(),
                'need_invest' => $needInvest->count(),
                'no_invest' => $noInvest->count(),
                'project' => $withProject->count(),
                'rejected' => $rejected->count(),
            ],
            'filters' => ['q' => $q, 'filter' => $filter],
            'can' => [
                'create' => $request->user()->can('create', Tooling::class),
            ],
        ]);
    }

    /** CSV (opens in Excel) of every non-deleted request, like the old "Ekspor Excel (CSV)". */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Tooling::class);

        $tools = Tooling::query()
            ->visibleTo($request->user())
            ->whereNull('deleted_at')
            ->with(['approvals', 'items', 'project'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $header = [
            'Doc No', 'NPD No', 'Client Code', 'Product Name', 'Marketing', 'Packdev', 'Request Date', 'Due Date (SLA 5WD)',
            'Status', 'Sample Status', 'Sample Sent', 'Sample Send Note', 'Sample Received (PIE)', 'Sample Receiver (PIE)',
            'Packaging Type', 'BOM Primary', 'Formula & Fill', 'Recommended Machine / Line', 'Need Invest', 'Need Invest Note',
            'Production Risk', 'Production Note', 'Tool Measurements', 'Sign PRNI', 'Sign RNI', 'Sign PIE', 'Sign Production',
            'Linked Project', 'Submitted By', 'Last Updated',
        ];

        $filename = 'check-tooling-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($tools, $header) {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('Cannot open the output stream.');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, $header);
            foreach ($tools as $t) {
                $project = $this->liveProject($t);
                fputcsv($out, [
                    $t->doc_code,
                    $t->npd_no,
                    $t->client_code,
                    $t->product_name,
                    $t->marketing,
                    $t->packdev,
                    $t->request_date?->toDateString(),
                    $t->due_date?->toDateString(),
                    $t->status,
                    $t->sample_status,
                    $t->sample_send_date?->toDateString(),
                    $t->sample_send_note,
                    $t->sample_received_date?->toDateString(),
                    $t->sample_received_by_name,
                    $t->packaging_type,
                    $t->bom_primary,
                    trim((string) $t->formula_info.($t->volume_suggest !== '' ? ' ('.$t->volume_suggest.')' : '')),
                    $t->recommended_machine,
                    $t->need_invest,
                    $t->need_invest_note,
                    $t->risk_level,
                    $t->production_note,
                    $t->items
                        ->filter(fn ($i) => $i->tool !== '' || $i->actual !== '')
                        ->map(fn ($i) => ($i->tool ?: '-').': '.($i->actual ?: '-').' ('.($i->status ?: 'OK').')')
                        ->implode('; '),
                    $this->signText($t, 'prni'),
                    $this->signText($t, 'rni'),
                    $this->signText($t, 'pi'),
                    $this->signText($t, 'production'),
                    $project !== null
                        ? $project->code.' - '.$project->name.' ('.$project->progress.'%)'
                        : ($t->need_invest === 'YES' ? 'Not created yet' : '-'),
                    $t->requested_by_name ?: $t->submitted_by_name,
                    $t->updated_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(int $tooling, PdfService $pdf): HttpResponse
    {
        $model = $this->find($tooling);
        Gate::authorize('view', $model);

        return $pdf->fromView('pdf.tooling', $this->formData($model), $this->filename($model, 'pdf'));
    }

    /** Word opens an HTML document saved as .doc, same trick as the old app. */
    public function word(int $tooling): HttpResponse
    {
        $model = $this->find($tooling);
        Gate::authorize('view', $model);

        return response(view('pdf.tooling', $this->formData($model))->render(), 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($model, 'doc').'"',
        ]);
    }

    private function find(int $id): Tooling
    {
        return Tooling::whereNull('deleted_at')->where('id', $id)->firstOrFail();
    }

    private function filename(Tooling $t, string $ext): string
    {
        $base = $t->npd_no !== '' ? $t->npd_no : ($t->product_name !== '' ? $t->product_name : 'FR-PRNI-002-00');

        return trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-').'.'.$ext;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Tooling $t): array
    {
        $t->load(['approvals', 'items']);

        return [
            'title' => ($t->doc_code ?: 'FR.PRNI.002.00').' '.$t->npd_no,
            'tooling' => $t,
            'tools' => $t->items->filter(fn ($i) => $i->tool !== '' || $i->actual !== '' || $i->status !== '')->values(),
            'approvals' => collect(ToolingOptions::ROLES)->mapWithKeys(fn (string $r) => [$r => $t->approvalFor($r)])->all(),
        ];
    }

    private function isNeedInvestPending(Tooling $t): bool
    {
        return $t->need_invest === 'YES' && $this->liveProject($t) === null;
    }

    private function liveProject(Tooling $t): ?Project
    {
        $project = $t->project;

        return $project !== null && $project->deleted_at === null ? $project : null;
    }

    private function signText(Tooling $t, string $role): string
    {
        $a = $t->approvalFor($role);

        return $a?->approved
            ? 'OK ('.($a->signer_name ?: '-').' · '.($a->sign_date?->toDateString() ?? '-').')'
            : 'Pending';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Tooling $t): array
    {
        $project = $this->liveProject($t);

        return [
            'id' => $t->id,
            'npd_no' => $t->npd_no,
            'product_name' => $t->product_name,
            'client_code' => $t->client_code,
            'packdev' => $t->packdev !== '' ? $t->packdev : $t->requested_by_name,
            'packaging_type' => $t->packaging_type,
            'product_type' => $t->product_type,
            'bom_primary' => $t->bom_primary,
            'quantity_po' => $t->quantity_po,
            'recommended_machine' => $t->recommended_machine,
            'risk_level' => $t->risk_level,
            'need_invest' => $t->need_invest,
            'is_critical' => $t->is_critical,
            'status' => $t->status,
            'signed' => collect(ToolingOptions::ROLES)->mapWithKeys(fn (string $r) => [$r => $t->isSigned($r)])->all(),
            'finished_at' => $t->updated_at?->format('Y-m-d H:i'),
            'need_invest_pending' => $t->status === ToolingOptions::STATUS_APPROVED && $this->isNeedInvestPending($t),
            'project' => $project ? ['id' => $project->id, 'code' => $project->code, 'progress' => (int) $project->progress] : null,
            'can_delete' => Gate::allows('delete', $t),
        ];
    }
}
