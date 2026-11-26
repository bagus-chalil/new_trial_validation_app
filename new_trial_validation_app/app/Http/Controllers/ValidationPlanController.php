<?php

namespace App\Http\Controllers;

use App\Actions\Validation\SaveValidationPlan;
use App\Models\ValidationAssessment;
use App\Models\ValidationCase;
use App\Models\ValidationPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trial Validation v2.0 Tahap 2: the weekly Validation Plan. PIE asks PPIC
 * for a trial week for a SKU (Case); PPIC confirms. Everybody reads.
 */
class ValidationPlanController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ValidationPlan::class);

        $f = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'week' => trim((string) $request->query('week', '')),
        ];

        $plans = ValidationPlan::query()
            ->whereNull('deleted_at')
            ->with('case:id,case_no,case_type')
            ->when($f['q'] !== '', function ($q) use ($f) {
                $like = '%'.addcslashes($f['q'], '%_\\').'%';
                $q->where(fn ($w) => $w->where('product_name', 'like', $like)
                    ->orWhereHas('case', fn ($c) => $c->where('case_no', 'like', $like)));
            })
            ->when(in_array($f['status'], ValidationPlan::STATUSES, true), fn ($q) => $q->where('status', $f['status']))
            ->when($f['week'] !== '', fn ($q) => $q->whereDate('week_start', Carbon::parse($f['week'])->startOfWeek()->toDateString()))
            ->orderByDesc('week_start')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $user = $request->user();
        $policy = Gate::forUser($user);

        $rows = $plans->getCollection()->map(fn (ValidationPlan $p) => [
            'id' => $p->id,
            'case_id' => $p->case_id,
            'case_no' => $p->case->case_no ?? null,
            'product_name' => $p->product_name,
            'week_start' => $p->week_start->toDateString(),
            'status' => $p->status,
            'notes' => $p->notes,
            'ppic_note' => $p->ppic_note,
            'requested_by_name' => $p->requested_by_name,
            'confirmed_by_name' => $p->confirmed_by_name,
            'confirmed_at' => $p->confirmed_at?->toDateString(),
            'can_confirm' => $p->status === 'Requested' && $policy->allows('confirm', $p),
            'can_cancel' => $p->status === 'Requested' && $policy->allows('cancel', $p),
        ]);

        // Cases that can be planned: every live Case with an Assessment.
        $cases = $policy->allows('create', ValidationPlan::class)
            ? ValidationAssessment::query()
                ->whereNull('vd_assessments.deleted_at')
                ->with('case:id,case_no')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->map(fn (ValidationAssessment $a) => [
                    'id' => $a->case_id,
                    'label' => ($a->case->case_no ?? '').' · '.$a->product_name,
                ])
                ->values()
            : [];

        return Inertia::render('validation/plans/index', [
            'plans' => [
                'data' => $rows->values(),
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
                'links' => $plans->linkCollection()->all(),
            ],
            'filters' => $f,
            'statuses' => ValidationPlan::STATUSES,
            'cases' => $cases,
            'can' => ['create' => $policy->allows('create', ValidationPlan::class)],
        ]);
    }

    public function store(Request $request, SaveValidationPlan $action): RedirectResponse
    {
        Gate::authorize('create', ValidationPlan::class);

        $data = $request->validate([
            'case_id' => ['required', 'integer', Rule::exists('vd_cases', 'id')->whereNull('deleted_at')],
            'week' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $case = ValidationCase::query()->whereNull('deleted_at')->where('id', (int) $data['case_id'])->firstOrFail();
        $action->request($case, $data['week'], $data['notes'] ?? null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.plan_requested')]);

        return back();
    }

    public function confirm(Request $request, int $plan, SaveValidationPlan $action): RedirectResponse
    {
        $model = ValidationPlan::query()->whereNull('deleted_at')->findOrFail($plan);
        Gate::authorize('confirm', $model);

        abort_unless($model->status === 'Requested', 422);

        $data = $request->validate(['ppic_note' => ['nullable', 'string', 'max:1000']]);
        $action->confirm($model, $data['ppic_note'] ?? null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.plan_confirmed')]);

        return back();
    }

    public function cancel(Request $request, int $plan, SaveValidationPlan $action): RedirectResponse
    {
        $model = ValidationPlan::query()->whereNull('deleted_at')->findOrFail($plan);
        Gate::authorize('cancel', $model);

        abort_unless($model->status === 'Requested', 422);

        $action->cancel($model, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.plan_cancelled')]);

        return back();
    }
}
