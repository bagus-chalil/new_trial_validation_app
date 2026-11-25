<?php

namespace App\Http\Controllers;

use App\Actions\Toolings\PromoteToolingToProject;
use App\Actions\Toolings\SaveTooling;
use App\Actions\Toolings\ToolingFiles;
use App\Actions\Toolings\ToolingSteps;
use App\Http\Requests\Toolings\SaveToolingRequest;
use App\Http\Requests\Toolings\ToolingStepRequest;
use App\Models\ProjectFile;
use App\Models\Tooling;
use App\Models\ToolingApproval;
use App\Models\User;
use App\Services\Projects\ToolingWorkflow;
use App\Support\ProjectOptions;
use App\Support\ToolingOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Check Tooling (FR.PRNI.002.00) of the Projects module, PROJECT_MODULE_PLAN.md
 * Fase 4b. List -> detail first; every workflow step is a separate POST from
 * the detail page (`step`), gated by ToolingPolicy and, for signatures, the
 * user's password.
 */
class ToolingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Tooling::class);

        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $scope = in_array($request->query('scope'), ['active', 'mine', 'all'], true) ? (string) $request->query('scope') : 'active';

        $all = Tooling::query()->whereNull('deleted_at')->with('approvals')->orderByDesc('id')->get();

        $kpi = [
            'active' => $all->filter(fn (Tooling $t) => ! ToolingWorkflow::isFinal($t))->count(),
            'waiting_rni_sample' => $all->filter(fn (Tooling $t) => in_array($t->status, [ToolingOptions::STATUS_WAITING_RNI_SAMPLE, ToolingOptions::STATUS_WAITING_RNI, ToolingOptions::STATUS_WAITING_SAMPLE], true))->count(),
            'pie_check' => $all->where('status', ToolingOptions::STATUS_PIE_CHECK)->count(),
            'production' => $all->where('status', ToolingOptions::STATUS_PRODUCTION)->count(),
            'approved' => $all->where('status', ToolingOptions::STATUS_APPROVED)->count(),
            'mine' => $all->filter(fn (Tooling $t) => ToolingWorkflow::pendingActionFor($t, $user) !== null)->count(),
        ];

        $items = $all
            ->filter(fn (Tooling $t) => match ($scope) {
                'active' => ! ToolingWorkflow::isFinal($t),
                'mine' => ToolingWorkflow::pendingActionFor($t, $user) !== null,
                default => true,
            })
            ->filter(fn (Tooling $t) => $status === '' || $t->status === $status)
            ->filter(function (Tooling $t) use ($q) {
                if ($q === '') {
                    return true;
                }
                $haystack = mb_strtolower(implode(' ', [$t->npd_no, $t->product_name, $t->client_code, $t->packaging_type, $t->submitted_by_name, $t->requested_by_name]));

                return str_contains($haystack, mb_strtolower($q));
            })
            ->take(300)
            ->map(fn (Tooling $t) => $this->summary($t, $user))
            ->values();

        return Inertia::render('toolings/index', [
            'items' => $items,
            'kpi' => $kpi,
            'statuses' => ToolingOptions::STATUSES,
            'filters' => ['q' => $q, 'status' => $status, 'scope' => $scope],
            'can' => ['create' => $user->can('create', Tooling::class)],
            'myRoles' => ToolingWorkflow::rolesFor($user),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Tooling::class);

        $user = $request->user();

        return Inertia::render('toolings/form', [
            'tooling' => null,
            'defaults' => [
                'npd_no' => ToolingWorkflow::nextNpdNo(),
                'request_date' => Carbon::today()->toDateString(),
                'requested_by_name' => $user->name,
                'packdev' => $user->name,
            ],
            'uploadLimitMb' => ProjectOptions::MAX_UPLOAD_BYTES / 1024 / 1024,
            'options' => $this->options(),
        ]);
    }

    public function store(SaveToolingRequest $request, SaveTooling $action, ToolingFiles $files): RedirectResponse
    {
        $tooling = $action(null, Arr::except($request->validated(), 'drawings'), $request->user());

        $failed = [];
        foreach ($request->file('drawings', []) as $upload) {
            try {
                $files->store($tooling, $upload, null, $request->user());
            } catch (InvalidArgumentException $e) {
                $failed[] = $e->getMessage();
            }
        }

        Inertia::flash('toast', $failed === []
            ? ['type' => 'success', 'message' => __('toolings.toast.created')]
            : ['type' => 'warning', 'message' => __('toolings.toast.drawings_failed', ['errors' => implode(' ', $failed)])]);

        return to_route('projects.toolings.show', $tooling);
    }

    public function show(Request $request, int $tooling): Response
    {
        $tooling = $this->find($tooling);
        Gate::authorize('view', $tooling);

        $user = $request->user();
        $tooling->load(['approvals', 'items']);

        $files = ProjectFile::where('tooling_id', $tooling->id)->orderByDesc('id')->get();
        $project = $tooling->project_id
            ? $tooling->project()->whereNull('deleted_at')->first(['id', 'name', 'code'])
            : null;

        $abilities = ['update', 'signPrni', 'sendSample', 'signRni', 'receiveSample', 'pieCheck', 'signProduction', 'hold', 'reject', 'resume', 'assign', 'attach', 'deleteFile', 'promote', 'delete'];

        return Inertia::render('toolings/show', [
            'tooling' => [
                ...$this->summary($tooling, $user),
                'doc_code' => $tooling->doc_code,
                'request_date' => $tooling->request_date?->toDateString(),
                'requested_by_name' => $tooling->requested_by_name,
                'submitted_at' => $tooling->submitted_at?->format('Y-m-d H:i'),
                'marketing' => $tooling->marketing,
                'packdev' => $tooling->packdev,
                'product_type' => $tooling->product_type,
                'quantity_po' => $tooling->quantity_po,
                'launch_date' => $tooling->launch_date,
                'dimension_packaging' => $tooling->dimension_packaging,
                'material_packaging' => $tooling->material_packaging,
                'bom_primary' => $tooling->bom_primary,
                'pm_ref_detail' => $tooling->pm_ref_detail,
                'links' => $this->links($tooling->links),
                'sample_send_date' => $tooling->sample_send_date?->toDateString(),
                'sample_send_note' => $tooling->sample_send_note,
                'sample_received_date' => $tooling->sample_received_date?->toDateString(),
                'sample_received_note' => $tooling->sample_received_note,
                'sample_received_by_name' => $tooling->sample_received_by_name,
                'formula_info' => $tooling->formula_info,
                'volume_suggest' => $tooling->volume_suggest,
                'recommended_machine' => $tooling->recommended_machine,
                'need_invest' => $tooling->need_invest,
                'need_invest_note' => $tooling->need_invest_note,
                'production_note' => $tooling->production_note,
                'risk_level' => $tooling->risk_level,
                'pi_owner_name' => $tooling->pi_owner_name,
                'status_reason' => $tooling->status_reason,
                'updated_by_name' => $tooling->updated_by_name,
                // Legacy forms kept blank tool rows; only filled ones are shown.
                'items' => $tooling->items
                    ->filter(fn ($i) => $i->tool !== '' || $i->actual !== '' || $i->status !== '')
                    ->map(fn ($i) => ['tool' => $i->tool, 'actual' => $i->actual, 'status' => $i->status])
                    ->values(),
                'approvals' => collect(ToolingOptions::ROLES)->map(fn (string $role) => $this->approval($role, $tooling->approvalFor($role), $tooling))->values(),
                'history' => $this->history($tooling->history_log),
            ],
            'project' => $project ? ['id' => $project->id, 'name' => $project->name, 'code' => $project->code] : null,
            'files' => $files->map(fn (ProjectFile $f) => [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'size_bytes' => $f->size_bytes,
                'description' => $f->description,
                'uploaded_by_name' => $f->uploaded_by_name,
                'uploaded_at' => $f->uploaded_at?->format('Y-m-d H:i'),
                'is_missing' => $f->is_missing,
                'url' => route('projects.toolings.files.show', [$tooling->id, $f->id]),
            ])->values(),
            'can' => collect($abilities)->mapWithKeys(fn (string $a) => [$a => $user->can($a, $tooling)])->all(),
            'candidates' => $user->can('assign', $tooling)
                ? collect(ToolingOptions::ROLES)->mapWithKeys(fn (string $r) => [$r => ToolingWorkflow::candidatesFor($r)])->all()
                : null,
            'defaultTools' => ToolingOptions::DEFAULT_TOOLS,
            'options' => $this->options(),
            'uploadLimitMb' => ProjectOptions::MAX_UPLOAD_BYTES / 1024 / 1024,
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    public function edit(Request $request, int $tooling): Response
    {
        $tooling = $this->find($tooling);
        Gate::authorize('update', $tooling);

        return Inertia::render('toolings/form', [
            'tooling' => [
                'id' => $tooling->id,
                'npd_no' => $tooling->npd_no,
                'request_date' => $tooling->request_date?->toDateString(),
                'requested_by_name' => $tooling->requested_by_name,
                'client_code' => $tooling->client_code,
                'product_name' => $tooling->product_name,
                'marketing' => $tooling->marketing,
                'packdev' => $tooling->packdev,
                'product_type' => $tooling->product_type,
                'packaging_type' => $tooling->packaging_type,
                'quantity_po' => $tooling->quantity_po,
                'launch_date' => $tooling->launch_date,
                'dimension_packaging' => $tooling->dimension_packaging,
                'material_packaging' => $tooling->material_packaging,
                'bom_primary' => $tooling->bom_primary,
                'pm_ref_detail' => $tooling->pm_ref_detail,
                'is_critical' => $tooling->is_critical,
                'links' => $this->links($tooling->links),
            ],
            'defaults' => null,
            'options' => $this->options(),
        ]);
    }

    public function update(SaveToolingRequest $request, int $tooling, SaveTooling $action): RedirectResponse
    {
        $tooling = $this->find($tooling);
        $action($tooling, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toolings.toast.updated')]);

        return to_route('projects.toolings.show', $tooling);
    }

    public function step(ToolingStepRequest $request, int $tooling, string $step, ToolingSteps $steps): RedirectResponse
    {
        $model = $request->tooling();
        $user = $request->user();
        $data = $request->validated();
        $note = isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null;

        match ($step) {
            'sign-prni' => $steps->signPrni($model, $user, $note),
            'send-sample' => $steps->sendSample($model, $user, (string) $data['date'], $note),
            'sign-rni' => $steps->signRni($model, $user, trim((string) $data['formula_info']), trim((string) $data['volume_suggest']), $note),
            'receive-sample' => $steps->receiveSample($model, $user, (string) $data['date'], $note),
            'pie-check' => $steps->pieCheck($model, $user, [
                'tools' => array_values((array) $data['tools']),
                'recommended_machine' => (string) $data['recommended_machine'],
                'need_invest' => (string) $data['need_invest'],
                'need_invest_note' => $data['need_invest_note'] ?? null,
                'production_note' => $data['production_note'] ?? null,
                'risk_level' => (string) $data['risk_level'],
                'note' => $note,
            ]),
            'sign-production' => $steps->signProduction($model, $user, $note),
            'hold' => $steps->hold($model, $user, trim((string) $data['reason'])),
            'reject' => $steps->reject($model, $user, trim((string) $data['reason'])),
            'resume' => $steps->resume($model, $user, $note),
            'assign' => $steps->assign($model, $user, (array) ($data['assignees'] ?? [])),
            default => abort(404),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toolings.toast.step.'.$step)]);

        return to_route('projects.toolings.show', $model);
    }

    public function promote(Request $request, int $tooling, PromoteToolingToProject $action): RedirectResponse
    {
        $tooling = $this->find($tooling);
        Gate::authorize('promote', $tooling);

        try {
            $project = $action($tooling, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['promote' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toolings.toast.promoted')]);

        return to_route('projects.show', $project);
    }

    public function destroy(Request $request, int $tooling, ToolingFiles $files): RedirectResponse
    {
        $tooling = $this->find($tooling);
        Gate::authorize('delete', $tooling);

        $files->deleteTooling($tooling, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toolings.toast.deleted')]);

        return $request->input('from') === 'report'
            ? to_route('projects.toolings.report')
            : to_route('projects.toolings.index');
    }

    private function find(int $id): Tooling
    {
        return Tooling::whereNull('deleted_at')->where('id', $id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Tooling $t, User $user): array
    {
        $due = $t->due_date;

        return [
            'id' => $t->id,
            'npd_no' => $t->npd_no,
            'product_name' => $t->product_name,
            'client_code' => $t->client_code,
            'packaging_type' => $t->packaging_type,
            'status' => $t->status,
            'sample_status' => $t->sample_status,
            'is_critical' => $t->is_critical,
            'submitted_by_name' => $t->submitted_by_name,
            'due_date' => $due?->toDateString(),
            'is_late' => $due !== null && $due->lt(Carbon::today()) && ! ToolingWorkflow::isFinal($t) && ! $t->isSigned('pi'),
            'signed' => collect(ToolingOptions::ROLES)->mapWithKeys(fn (string $r) => [$r => $t->isSigned($r)])->all(),
            'bottleneck' => ToolingWorkflow::bottleneck($t),
            'my_action' => ToolingWorkflow::pendingActionFor($t, $user),
            'project_id' => $t->project_id,
            'updated_at' => $t->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function approval(string $role, ?ToolingApproval $a, Tooling $tooling): array
    {
        $image = $a?->signature_image;
        if (! is_string($image) || preg_match('#^data:image/(?:png|jpeg);base64,[A-Za-z0-9+/=]+$#', $image) !== 1) {
            $image = null;
        }

        return [
            'role' => $role,
            'assignee_id' => $tooling->assigneeFor($role),
            'assignee_name' => $this->assigneeName($tooling->assigneeFor($role)),
            'approved' => (bool) $a?->approved,
            'signer_name' => $a->signer_name ?? '',
            'sign_date' => $a?->sign_date?->toDateString(),
            'signed_at' => $a?->signed_at?->format('Y-m-d H:i'),
            'note' => $a?->note,
            'method' => $a->signature_method ?? '',
            'signed_by_account' => $a->signed_by_account ?? '',
            'signature_image' => $image,
        ];
    }

    private function assigneeName(?int $id): ?string
    {
        return $id === null ? null : User::query()->whereKey($id)->value('name');
    }

    /**
     * Links are {title, url}; imported ones may also carry an id/description.
     * Only http(s) URLs are passed to the page.
     *
     * @param  array<int, mixed>|null  $links
     * @return list<array{title: string, url: string}>
     */
    private function links(?array $links): array
    {
        $out = [];
        foreach ($links ?? [] as $link) {
            $url = is_array($link) ? (string) ($link['url'] ?? '') : (string) $link;
            if (preg_match('#^https?://#i', $url) !== 1) {
                continue;
            }
            $out[] = ['title' => is_array($link) ? (string) ($link['title'] ?? '') : '', 'url' => $url];
        }

        return $out;
    }

    /**
     * Normalise the timeline. Entries written here: {at: 'Y-m-d H:i:s', by,
     * status, action: CODE, note}; imported ones: {at: epoch-ms, by, status,
     * action: free text, note}. `code` is true for the former so the page can
     * translate it.
     *
     * @param  array<int, mixed>|null  $log
     * @return list<array{at: string, by: string, status: string, action: string, note: string, code: bool}>
     */
    private function history(?array $log): array
    {
        $out = [];
        foreach ($log ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $at = $entry['at'] ?? '';
            $legacy = is_int($at) || is_float($at) || (is_string($at) && ctype_digit($at));
            if ($legacy) {
                $at = Carbon::createFromTimestampMs((int) $at)->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i');
            } else {
                $at = mb_substr((string) $at, 0, 16);
            }
            $action = (string) ($entry['action'] ?? '');
            $out[] = [
                'at' => (string) $at,
                'by' => (string) ($entry['by'] ?? ''),
                'status' => (string) ($entry['status'] ?? ''),
                'action' => $action,
                'note' => (string) ($entry['note'] ?? ''),
                'code' => ! $legacy && preg_match('/^[A-Z_]+$/', $action) === 1,
            ];
        }

        return array_reverse($out);
    }

    /**
     * @return array<string, list<string>>
     */
    private function options(): array
    {
        return [
            'product_types' => ToolingOptions::PRODUCT_TYPES,
            'tool_statuses' => ToolingOptions::TOOL_STATUSES,
            'invest_values' => ToolingOptions::INVEST_VALUES,
            'risk_levels' => ToolingOptions::RISK_LEVELS,
        ];
    }
}
