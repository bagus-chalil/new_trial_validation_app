<?php

namespace App\Http\Controllers;

use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\SaveProject;
use App\Http\Requests\Projects\SaveProjectRequest;
use App\Models\ActivityLog;
use App\Models\MasterOption;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectStage;
use App\Models\PurchaseOrder;
use App\Models\Trial;
use App\Models\User;
use App\Support\ProjectOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Projects module core (PROJECT_MODULE_PLAN.md Fase 2): portfolio, project
 * form and the detail page. Stages, milestones, quotations and files each
 * have their own controller (one controller per feature slice).
 */
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'category' => (string) $request->query('category', ''),
            'priority' => (string) $request->query('priority', ''),
            'pic' => (string) $request->query('pic', ''),
        ];

        $projects = Project::query()
            ->whereNull('deleted_at')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('pic_name', 'like', $like)
                    ->orWhere('vendor', 'like', $like));
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['category'] !== '', fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['priority'] !== '', fn ($query) => $query->where('priority', $filters['priority']))
            ->when($filters['pic'] !== '', fn ($query) => $query->where('pic_name', $filters['pic']))
            ->orderByRaw("CASE status WHEN 'Completed' THEN 1 ELSE 0 END")
            ->orderBy('end_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Project $p) => $this->summary($p));

        $today = Carbon::today();
        $all = Project::query()->whereNull('deleted_at')->get(['id', 'status', 'end_date', 'progress']);
        $open = $all->where('status', '!=', 'Completed');

        $kpi = [
            'total' => $all->count(),
            'active' => $all->where('status', 'On Progress')->count(),
            'completed' => $all->where('status', 'Completed')->count(),
            'delayed' => $open->filter(fn ($p) => $p->status === 'Delayed' || ($p->end_date && $p->end_date->lt($today)))->count(),
            'avg_progress' => $open->isEmpty() ? 0 : (int) round($open->avg('progress')),
        ];

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => $filters,
            'kpi' => $kpi,
            'activeProjects' => $this->activeProjects(),
            'attention' => $this->attention($today),
            'options' => [
                'statuses' => ProjectOptions::PROJECT_STATUSES,
                'priorities' => ProjectOptions::PRIORITIES,
                'categories' => $this->categories(),
                'pics' => Project::query()->whereNull('deleted_at')->where('pic_name', '!=', '')
                    ->distinct()->orderBy('pic_name')->pluck('pic_name'),
            ],
            'can' => ['create' => $request->user()->can('create', Project::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/form', [
            'project' => null,
            'options' => $this->formOptions(),
            'defaults' => ['pic_user_id' => $request->user()->id],
        ]);
    }

    public function store(SaveProjectRequest $request, SaveProject $action): RedirectResponse
    {
        $project = $action(null, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.created')]);

        return to_route('projects.show', $project);
    }

    public function show(Request $request, int $project): Response
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        Gate::authorize('view', $project);

        $user = $request->user();
        $project->load(['stages.trial', 'milestones', 'quotations', 'machine', 'product']);
        $canFinance = Gate::forUser($request->user())->allows('access-finance');
        $canUpdate = $user->can('update', $project);

        $files = ProjectFile::where('project_id', $project->id)->orderByDesc('id')->get();
        $fileUrl = fn (ProjectFile $f) => route('projects.files.show', [$project->id, $f->id]);
        $mapFile = fn (ProjectFile $f) => [
            'id' => $f->id,
            'stage_id' => $f->stage_id,
            'context' => $f->context,
            'file_name' => $f->file_name,
            'mime_type' => $f->mime_type,
            'size_bytes' => $f->size_bytes,
            'description' => $f->description,
            'uploaded_by_name' => $f->uploaded_by_name,
            'uploaded_at' => $f->uploaded_at?->format('Y-m-d H:i'),
            'is_missing' => $f->is_missing,
            'url' => $fileUrl($f),
        ];
        $filesById = $files->keyBy('id');

        $logs = ActivityLog::query()
            ->where('module', 'PROJECT')
            ->where(function ($w) use ($project) {
                $w->where('record_id', (string) $project->id);

                // Imported legacy history: `record_id` is the legacy log row id,
                // the owning project's legacy id sits in `new_data.scope`.
                if ($project->legacy_id) {
                    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $project->legacy_id);
                    $w->orWhere(fn ($legacy) => $legacy
                        ->where('action', 'LEGACY_LOG')
                        ->where('new_data', 'like', '%"scope":"'.$like.'"%'));
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'user_name', 'action', 'old_data', 'new_data', 'created_at']);

        return Inertia::render('projects/show', [
            'project' => $this->detail($project),
            'stages' => $project->stages->map(fn (ProjectStage $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'stage_type' => $s->stage_type,
                'pic_name' => $s->pic_name,
                'plan_start' => $s->plan_start?->format('Y-m-d'),
                'plan_end' => $s->plan_end?->format('Y-m-d'),
                'actual_start' => $s->actual_start?->format('Y-m-d'),
                'actual_end' => $s->actual_end?->format('Y-m-d'),
                'progress' => $s->progress,
                'is_parallel' => $s->is_parallel,
                'status' => $s->status,
                'note' => $s->note,
                'trial_id' => $s->trial_id,
                'trial' => $this->linkedTrial($s, $user),
            ])->values(),
            'linkableTrials' => $canUpdate ? $this->linkableTrials($user) : [],
            'milestones' => $project->milestones->sortBy('due_date')->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'due_date' => $m->due_date?->format('Y-m-d'),
                'status' => $m->status,
                'note' => $m->note,
            ])->values(),
            'quotations' => ! $canFinance ? [] : $project->quotations->map(fn ($q) => [
                'id' => $q->id,
                'vendor_name' => $q->vendor_name,
                'quotation_no' => $q->quotation_no,
                'amount' => (float) $q->amount,
                'currency' => $q->currency,
                'quotation_date' => $q->quotation_date?->format('Y-m-d'),
                'valid_until' => $q->valid_until?->format('Y-m-d'),
                'status' => $q->status,
                'note' => $q->note,
                'link' => $q->link,
                'file' => ($f = $q->file_id ? $filesById->get($q->file_id) : null) ? $mapFile($f) : null,
            ])->values(),
            'purchaseOrders' => ! $canFinance ? [] : PurchaseOrder::query()->where('project_id', $project->id)->with('items')->orderByDesc('po_date')->orderByDesc('id')->get()->map(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'po_no' => $po->po_no,
                'po_date' => $po->po_date?->format('Y-m-d'),
                'vendor_name' => $po->vendor_name,
                'subject' => $po->subject,
                'currency' => $po->currency,
                'total' => $po->totals()['total'],
            ])->values(),
            'files' => $files->where('context', '!=', 'quotation')->map($mapFile)->values(),
            'logs' => $logs->map(fn (ActivityLog $l) => [
                'id' => $l->id,
                'user_name' => $l->user_name,
                'action' => $l->action,
                'detail' => $this->logDetail($l),
                'created_at' => $l->created_at?->format('Y-m-d H:i'),
            ]),
            'options' => [
                'projectStatuses' => ProjectOptions::PROJECT_STATUSES,
                'stageStatuses' => ProjectOptions::STAGE_STATUSES,
                'stageTypes' => ProjectOptions::STAGE_TYPES,
                'milestoneStatuses' => ProjectOptions::MILESTONE_STATUSES,
                'quotationStatuses' => ProjectOptions::QUOTATION_STATUSES,
                'vendors' => $this->vendors(),
            ],
            'can' => [
                'update' => $canUpdate,
                'delete' => $user->can('delete', $project),
                'createTrial' => $canUpdate && $user->can('create', Trial::class),
                'purchaseOrder' => $user->can('create', PurchaseOrder::class),
                'finance' => $canFinance,
            ],
            'uploadLimitMb' => ProjectOptions::MAX_UPLOAD_BYTES / 1024 / 1024,
            'allowedExtensions' => array_keys(ProjectOptions::UPLOAD_MIMES),
        ]);
    }

    public function edit(int $project): Response
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        Gate::authorize('update', $project);

        return Inertia::render('projects/form', [
            'project' => $this->detail($project) + [
                'has_stages' => $project->stages()->exists(),
            ],
            'options' => $this->formOptions(),
            'defaults' => null,
        ]);
    }

    public function update(SaveProjectRequest $request, int $project, SaveProject $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        $action($project, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.updated')]);

        return to_route('projects.show', $project);
    }

    public function destroy(Request $request, int $project, DeleteProject $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        Gate::authorize('delete', $project);

        $action($project, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.deleted')]);

        return to_route('projects.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Project $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'category' => $p->category,
            'status' => $p->status,
            'priority' => $p->priority,
            'pic_name' => $p->pic_name,
            'start_date' => $p->start_date?->format('Y-m-d'),
            'end_date' => $p->end_date?->format('Y-m-d'),
            'progress' => $p->progress,
            'is_overdue' => $p->status !== 'Completed' && $p->end_date !== null && $p->end_date->lt(Carbon::today()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Project $p): array
    {
        return $this->summary($p) + [
            'pic_user_id' => $p->pic_user_id,
            'area' => $p->area,
            'description' => $p->description,
            'vendor' => $p->vendor,
            'po' => $p->po,
            'created_by_name' => $p->created_by_name,
            'updated_by_name' => $p->updated_by_name,
            'updated_at' => $p->updated_at?->format('Y-m-d H:i'),
            'machine_option_id' => $p->machine_option_id,
            'machine_name' => $p->machine?->name,
            'product_id' => $p->product_id,
            'product_name' => $p->product?->product_name,
            'finish_good_code' => $p->product?->finish_good_code,
        ];
    }

    /**
     * Human-readable detail for a history row. Only imported legacy rows
     * carry one (their original message sits in `new_data.text`); rows
     * written by this app are described by their action label alone.
     */
    private function logDetail(ActivityLog $log): ?string
    {
        if ($log->action !== 'LEGACY_LOG') {
            return null;
        }

        $data = json_decode((string) $log->new_data, true);
        $text = is_array($data) && is_string($data['text'] ?? null) ? trim($data['text']) : '';

        return $text !== '' ? $text : null;
    }

    /**
     * The Trial linked to a Validation stage, or null. A soft-deleted trial
     * is still shown (so the link isn't silently lost) but flagged.
     *
     * @return array<string, mixed>|null
     */
    private function linkedTrial(ProjectStage $stage, User $user): ?array
    {
        $trial = $stage->trial;

        if (! $trial) {
            return null;
        }

        return [
            'id' => $trial->id,
            'trial_code' => $trial->trial_code,
            'product_name' => $trial->product_name,
            'progress_status' => $trial->progress_status,
            'final_decision' => $trial->final_decision,
            'is_deleted' => $trial->deleted_at !== null,
            'can_view' => $trial->deleted_at === null && $user->can('view', $trial),
        ];
    }

    /**
     * Trials the user may link to a Validation stage: not deleted, visible
     * to them, and not already linked to another stage. Newest first.
     *
     * @return list<array{value: string, label: string}>
     */
    private function linkableTrials(User $user): array
    {
        $taken = ProjectStage::query()->whereNotNull('trial_id')->pluck('trial_id');

        return array_values(Trial::query()
            ->whereNull('deleted_at')
            ->whereNotIn('id', $taken)
            ->orderByDesc('id')
            ->limit(300)
            ->with('lineConfigurationReport')
            ->get()
            ->filter(fn (Trial $t) => $user->can('view', $t))
            ->map(fn (Trial $t) => [
                'value' => (string) $t->id,
                'label' => $t->trial_code.' · '.$t->product_name.' ('.$t->progress_status.')',
            ])
            ->all());
    }

    /**
     * Up to 6 On-Progress projects, closest deadline first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activeProjects(): array
    {
        return Project::query()
            ->whereNull('deleted_at')
            ->where('status', 'On Progress')
            ->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('end_date')
            ->limit(6)
            ->get()
            ->map(fn (Project $p) => $this->summary($p))
            ->all();
    }

    /**
     * "Needs attention": not Completed and either Delayed/On Hold, past its
     * end date, or having an unfinished stage past its plan end.
     *
     * @return array<int, array<string, mixed>>
     */
    private function attention(Carbon $today): array
    {
        return Project::query()
            ->whereNull('deleted_at')
            ->where('status', '!=', 'Completed')
            ->with('stages')
            ->get()
            ->map(function (Project $p) use ($today) {
                $reasons = [];

                if ($p->status === 'Delayed') {
                    $reasons[] = 'delayed';
                }

                if ($p->status === 'On Hold') {
                    $reasons[] = 'on_hold';
                }

                if ($p->end_date && $p->end_date->lt($today)) {
                    $reasons[] = 'overdue';
                }

                $lateStages = $p->stages->filter(fn (ProjectStage $s) => $s->status !== 'Completed'
                    && $s->plan_end !== null && $s->plan_end->lt($today))->count();

                if ($lateStages > 0) {
                    $reasons[] = 'late_stages';
                }

                return $reasons === [] ? null : $this->summary($p) + ['reasons' => $reasons, 'late_stages' => $lateStages];
            })
            ->filter()
            ->sortBy(fn ($p) => $p['end_date'] ?? '9999-12-31')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        $fromMaster = MasterOption::query()->where('type', 'project_category')->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
        $fromData = Project::query()->whereNull('deleted_at')->distinct()->pluck('category')->all();

        return array_values(array_unique(array_filter([...$fromMaster, ...$fromData])));
    }

    /**
     * @return list<string>
     */
    private function vendors(): array
    {
        return array_values(MasterOption::query()->where('type', 'project_vendor')->where('is_active', 1)
            ->orderBy('name')->pluck('name')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => ProjectOptions::PROJECT_STATUSES,
            'priorities' => ProjectOptions::PRIORITIES,
            'categories' => $this->categories(),
            'users' => User::query()->where('is_active', 1)->whereNull('deleted_at')
                ->whereIn('role', ['Staff', 'Admin', 'Super Admin'])
                ->orderBy('name')->get(['id', 'name', 'department'])
                ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name.($u->department ? ' · '.$u->department : '')]),
            'machines' => MasterOption::query()->where('type', 'machine_used')->where('is_active', 1)->whereNull('deleted_at')
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (MasterOption $m) => ['value' => (string) $m->id, 'label' => $m->name]),
            'products' => Product::query()->where('is_active', 1)->whereNull('deleted_at')
                ->orderBy('product_name')->get(['id', 'product_name', 'finish_good_code'])
                ->map(fn (Product $p) => ['value' => (string) $p->id, 'label' => $p->product_name.($p->finish_good_code ? ' · '.$p->finish_good_code : '')]),
        ];
    }
}
