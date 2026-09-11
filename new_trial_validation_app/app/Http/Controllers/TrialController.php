<?php

namespace App\Http\Controllers;

use App\Actions\Trials\CreateTrial;
use App\Actions\Trials\UpdateTrial;
use App\Http\Requests\Trials\StoreTrialRequest;
use App\Http\Requests\Trials\UpdateTrialRequest;
use App\Models\MasterOption;
use App\Models\Product;
use App\Models\Trial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
     * URL segment => [internal status group, page title, page subtitle].
     * Copied verbatim from the legacy $map array (public/index.php:223-229),
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
        'approved' => ['approved', 'Approved Trials', 'Daftar trial dengan status approved.'],
        'tracking' => ['tracking', 'Tracking Proses', 'Pantau semua trial yang sedang berjalan (In Review & Ready for Approval) — halaman ini untuk memantau saja, aksi review/approve dilakukan lewat Need Review / Need Approval.'],
        'need-revision' => ['need-revision', 'Need Revision Trials', 'Trial yang dikembalikan ke Staff untuk direvisi.'],
        'rejected' => ['rejected', 'Rejected Trials', 'Trial yang ditolak final.'],
        'draft' => ['draft', 'Draft Trials', 'Trial yang masih berupa draft.'],
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

    public function create(): Response
    {
        Gate::authorize('create', Trial::class);

        return Inertia::render('trials/form', [
            'mode' => 'create',
            'trial' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreTrialRequest $request, CreateTrial $action): RedirectResponse
    {
        $trial = $action($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trial berhasil dibuat.']);

        return to_route('trials.validation.edit', $trial);
    }

    public function edit(int $trial): Response
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        Gate::authorize('update', $trial);

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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trial berhasil diperbarui.']);

        // Only a still-Draft trial is mid-wizard, so only it continues into
        // Validation. A Need Revision/In Review edit is a standalone
        // correction to an already-submitted trial — send it back to the
        // Report Summary page instead, matching this app's detail-then-act
        // navigation used everywhere else a non-Draft trial is acted on.
        if ($trial->progress_status !== 'Draft') {
            return to_route('trials.report.show', $trial);
        }

        return to_route('trials.validation.edit', $trial);
    }

    public function index(Request $request, string $group): Response
    {
        abort_unless(array_key_exists($group, self::GROUPS), 404);

        [$statusGroup, $title, $subtitle] = self::GROUPS[$group];
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
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $trials->getCollection()->each(function (Trial $trial) use ($user) {
            $trial->setAttribute('can_edit', Gate::forUser($user)->allows('update', $trial));
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
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle,
            'group' => $group,
            'canCreateTrial' => Gate::forUser($user)->allows('create', Trial::class),
        ]);
    }
}
