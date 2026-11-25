<?php

namespace App\Http\Controllers;

use App\Actions\Projects\LinkProjectStageTrial;
use App\Actions\Trials\CreateTrial;
use App\Actions\Trials\UpdateTrial;
use App\Http\Requests\Trials\StoreTrialRequest;
use App\Http\Requests\Trials\UpdateTrialRequest;
use App\Models\MasterOption;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationTrialMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Port of the 5 status-grouped trial list pages in the legacy app's
 * public/index.php (lines 221-241): /trials/approved, /trials/in-review,
 * /trials/need-revision, /trials/rejected, /trials/waiting-approval. All
 * share one query shape (Trial::scopeVisibleTo() + Trial::scopeSearch()),
 * just like legacy's trials_list.php view.
 *
 * Fase 3 adds create/edit (the trial header form, port of /trials/create,
 * /trials/store, /trials/{id}/edit, /trials/{id}/update) — see
 * App\Actions\Trials\CreateTrial / UpdateTrial for the actual write logic.
 * index() itself stays read-only.
 */
class TrialController extends Controller
{
    /**
     * URL segment => [internal status group, lang key under trials.list.groups].
     * The title/subtitle text lives in the `trials` lang group and started
     * from the legacy $map array (public/index.php:223-229),
     * plus a 'draft' group Fase 3 needs so a newly-created trial has
     * somewhere to be listed (Trial::scopeVisibleTo() already supports it).
     *
     * 'tracking' replaces the old separate 'in-review'/'waiting-approval'
     * segments (2026-09-11 menu restructure, direct user request): a single
     * monitor-only page covering every trial actively moving through the
     * process (In Review + Ready for Approval). It has no action buttons on
     * purpose — reviewing/approving happens on "Need Review"/"Need Approval"
     * (ReviewController/ApprovalController) or the per-trial Report Summary
     * page, never here.
     */
    private const GROUPS = [
        'approved' => ['approved', 'approved'],
        'tracking' => ['tracking', 'tracking'],
        'need-revision' => ['need-revision', 'need_revision'],
        'rejected' => ['rejected', 'rejected'],
        'draft' => ['draft', 'draft'],
    ];

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $option = fn (string $type) => MasterOption::query()
            ->where('type', $type)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        return [
            'products' => Product::query()
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->orderBy('product_name')
                ->get(['id', 'product_name', 'finish_good_code']),
            'masterOptions' => [
                'product_type' => $option('product_type'),
                'validation_category' => $option('validation_category'),
                'validation_scope' => $option('validation_scope'),
                'machine_used' => $option('machine_used'),
            ],
            'riskLevels' => ['Low', 'Medium', 'High'],
        ];
    }

    public function create(Request $request): Response|RedirectResponse
    {
        Gate::authorize('create', Trial::class);

        // Projects module Fase 4: "Buat Draft Trial" from a Validation stage
        // opens this same form, pre-filled from the project, and links the
        // new trial to the stage on save.
        $projectStage = null;
        $prefill = null;
        if ($request->filled('project_stage')) {
            try {
                [$project, $stage] = $this->linkableStage((int) $request->query('project_stage'), $request->user());
            } catch (ValidationException $e) {
                Inertia::flash('toast', ['type' => 'error', 'message' => (string) collect($e->errors())->flatten()->first()]);

                return back();
            }

            $project->loadMissing(['machine', 'product']);
            $product = $project->product;
            $machine = $project->machine;
            $projectStage = [
                'id' => $stage->id,
                'name' => $stage->name,
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_code' => $project->code,
            ];
            $prefill = [
                'product_id' => $product && $product->is_active && $product->deleted_at === null ? $product->id : null,
                'machine_used' => $machine && $machine->is_active && $machine->deleted_at === null ? [$machine->name] : [],
            ];
        }

        return Inertia::render('trials/form', [
            'mode' => 'create',
            'trial' => null,
            'projectStage' => $projectStage,
            'prefill' => $prefill,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreTrialRequest $request, CreateTrial $action, LinkProjectStageTrial $linker): RedirectResponse
    {
        $data = $request->validated();
        $stageId = $data['project_stage_id'] ?? null;
        unset($data['project_stage_id']);
        $user = $request->user();

        if ($stageId === null) {
            $trial = $action($data, $user);
        } else {
            // Check the stage first so a stage that can't take a trial never
            // leaves an orphan draft behind.
            [$project, $stage] = $this->linkableStage((int) $stageId, $user, 'project_stage_id');

            $trial = DB::transaction(function () use ($action, $linker, $data, $user, $project, $stage) {
                $trial = $action($data, $user);
                $linker->link($project, $stage, $trial, $user);

                return $trial;
            });
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.toast.trial_created')]);

        return to_route('trials.validation.edit', $trial);
    }

    /**
     * A Validation stage (of a live project the user may change) that has no
     * trial yet.
     *
     * @return array{0: Project, 1: ProjectStage}
     */
    private function linkableStage(int $stageId, User $user, string $errorKey = 'trial_id'): array
    {
        $stage = ProjectStage::query()->findOrFail($stageId);
        $project = Project::query()->whereNull('deleted_at')->findOrFail($stage->project_id);

        Gate::forUser($user)->authorize('update', $project);

        try {
            LinkProjectStageTrial::assertLinkable($stage);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([$errorKey => collect($e->errors())->flatten()->all()]);
        }

        return [$project, $stage];
    }

    public function edit(int $trial): Response
    {
        $trial = Trial::whereNull('deleted_at')->withCount('attachments')->findOrFail($trial);

        Gate::authorize('update', $trial);

        // Trial Validation v2.0 side-table dates (vd_trial_meta), shown on the form.
        $meta = Schema::hasTable('vd_trial_meta') ? ValidationTrialMeta::query()->where('trial_id', $trial->id)->first() : null;
        $trial->setAttribute('pm_ready_date', $meta && ! $meta->dates_defaulted ? $meta->pm_ready_date?->toDateString() : null);
        $trial->setAttribute('bulk_ready_date', $meta && ! $meta->dates_defaulted ? $meta->bulk_ready_date?->toDateString() : null);

        return Inertia::render('trials/form', [
            'mode' => 'edit',
            'trial' => $trial,
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateTrialRequest $request, int $trial, UpdateTrial $action): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        $action($trial, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.toast.trial_updated')]);

        // TrialPolicy::update() only lets this succeed for Draft, Need
        // Revision, or In-Review-before-any-department-has-reviewed — every
        // case where the owner is meant to be able to redo the trial from
        // the start (validation/weighing/attachments), not just patch the
        // header and stop. So always continue into the wizard here; the
        // review/approval decisions themselves stay untouched regardless,
        // since this only ever writes trials_header, never trials_review or
        // the approval fields.
        return to_route('trials.validation.edit', $trial);
    }

    /**
     * "Hapus" — soft delete (sets deleted_at/deleted_by, same columns and
     * mechanism the existing admin-only Trash/restore page already reads),
     * ported per direct user request (2026-10-06) mirroring ipc_app's
     * delete-to-Recycle-Bin pattern. TrialPolicy::delete() restricts this to
     * the trial's own Draft (owner/edit-permission-holder) or Admin for any
     * status — not exposed anywhere in the UI beyond Draft, matching the
     * user's literal "hapus draft" ask.
     */
    public function destroy(Request $request, int $trial): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        Gate::authorize('delete', $trial);

        $trial->deleted_at = Carbon::now();
        $trial->deleted_by = $request->user()->id;
        $trial->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.toast.trial_deleted', ['code' => $trial->trial_code]),
        ]);

        return to_route('my-work');
    }

    public function index(Request $request, string $group): Response
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        [$statusGroup, $langKey] = self::GROUPS[$group];
        $user = $request->user();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'product_type' => trim((string) $request->query('product_type', '')),
            'validation_scope' => trim((string) $request->query('validation_scope', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            // Only meaningful on the merged 'tracking' group (In Review +
            // Ready for Approval) — lets that page's own status sub-filter
            // narrow to one of the two without needing a separate group.
            // Harmless no-op elsewhere since scopeSearch() ANDs it onto a
            // statusGroup that's already fixed to a single status.
            'status' => trim((string) $request->query('status', '')),
        ];

        $trials = Trial::query()
            ->visibleTo($user, $statusGroup)
            ->search($filters)
            ->withCount('attachments')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $trials->getCollection()->each(function (Trial $trial) use ($user) {
            $trial->setAttribute('can_edit', Gate::forUser($user)->allows('update', $trial));
            $trial->setAttribute('can_delete', Gate::forUser($user)->allows('delete', $trial));
            $trial->setAttribute('can_archive', Gate::forUser($user)->allows('archive', $trial));
        });

        $option = fn (string $type) => MasterOption::query()
            ->where('type', $type)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        return Inertia::render('trials/index', [
            'trials' => $trials,
            'filters' => $filters,
            'productTypes' => $option('product_type'),
            'validationScopes' => $option('validation_scope'),
            'pageTitle' => __("trials.list.groups.{$langKey}.title"),
            'pageSubtitle' => __("trials.list.groups.{$langKey}.subtitle"),
            'group' => $group,
            'canCreateTrial' => Gate::forUser($user)->allows('create', Trial::class),
        ]);
    }
}
