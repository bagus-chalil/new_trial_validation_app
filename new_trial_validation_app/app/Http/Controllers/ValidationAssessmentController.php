<?php

namespace App\Http\Controllers;

use App\Actions\Validation\SaveValidationAssessment;
use App\Http\Requests\Validation\SaveValidationAssessmentRequest;
use App\Models\ActivityLog;
use App\Models\MasterOption;
use App\Models\Product;
use App\Models\ValidationAssessment;
use App\Models\ValidationCaseLink;
use App\Support\ValidationCaseType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trial Validation v2.0 Tahap 2: the Validation Assessment register (ADD NEW
 * DATA). Saving a new Assessment creates the Case (see SaveValidationAssessment).
 */
class ValidationAssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ValidationAssessment::class);

        $f = [
            'q' => trim((string) $request->query('q', '')),
            'case_type' => trim((string) $request->query('case_type', '')),
            'risk_level' => trim((string) $request->query('risk_level', '')),
            'client' => trim((string) $request->query('client', '')),
            'packaging_type' => trim((string) $request->query('packaging_type', '')),
            'sales_month' => trim((string) $request->query('sales_month', '')),
            'receive_from' => trim((string) $request->query('receive_from', '')),
            'receive_to' => trim((string) $request->query('receive_to', '')),
        ];

        $like = fn (string $v) => '%'.addcslashes($v, '%_\\').'%';

        $assessments = ValidationAssessment::query()
            ->whereNull('vd_assessments.deleted_at')
            ->with(['case:id,case_no,case_type', 'product:id,product_name,finish_good_code'])
            ->when($f['q'] !== '', fn ($q) => $q->where(function ($q) use ($f, $like) {
                $q->where('product_name', 'like', $like($f['q']))
                    ->orWhere('client', 'like', $like($f['q']))
                    ->orWhere('qco_no', 'like', $like($f['q']))
                    ->orWhereHas('case', fn ($c) => $c->where('case_no', 'like', $like($f['q'])))
                    ->orWhereHas('product', fn ($p) => $p->where('product_name', 'like', $like($f['q']))->orWhere('finish_good_code', 'like', $like($f['q'])));
            }))
            ->when(in_array($f['case_type'], ValidationCaseType::ALL, true), fn ($q) => $q->whereHas('case', fn ($c) => $c->where('case_type', $f['case_type'])))
            ->when(in_array($f['risk_level'], ['Low', 'Medium', 'High'], true), fn ($q) => $q->where('risk_level', $f['risk_level']))
            ->when($f['client'] !== '', fn ($q) => $q->where('client', 'like', $like($f['client'])))
            ->when($f['packaging_type'] !== '', fn ($q) => $q->where('packaging_type', $f['packaging_type']))
            ->when($f['sales_month'] !== '', fn ($q) => $q->where('sales_month', $f['sales_month']))
            ->when($f['receive_from'] !== '', fn ($q) => $q->whereDate('receive_date', '>=', $f['receive_from']))
            ->when($f['receive_to'] !== '', fn ($q) => $q->whereDate('receive_date', '<=', $f['receive_to']))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $trialCounts = ValidationCaseLink::query()
            ->where('kind', 'trial')
            ->whereIn('case_id', $assessments->pluck('case_id'))
            ->selectRaw('case_id, count(*) as c')
            ->groupBy('case_id')
            ->pluck('c', 'case_id');

        $user = $request->user();
        $canCreate = Gate::forUser($user)->allows('create', ValidationAssessment::class);

        $rows = $assessments->getCollection()->map(fn (ValidationAssessment $a) => [
            'id' => $a->id,
            'case_id' => $a->case_id,
            'case_no' => $a->case->case_no ?? null,
            'case_type' => $a->case->case_type ?? null,
            'product_id' => $a->product_id,
            'product_name' => $a->product->product_name ?? $a->product_name,
            'finish_good_code' => $a->product?->finish_good_code,
            'receive_date' => $a->receive_date?->toDateString(),
            'client' => $a->client,
            'sales_month' => $a->sales_month,
            'qty_po' => $a->qty_po,
            'packaging_type' => $a->packaging_type,
            'validation_scope' => $a->validation_scope ?? [],
            'recommended_machine' => $a->recommended_machine,
            'qco_no' => $a->qco_no,
            'qco_link' => $a->qco_link,
            'pm_received_date' => $a->pm_received_date?->toDateString(),
            'pm_na' => $a->pm_na,
            'bulk_received_date' => $a->bulk_received_date?->toDateString(),
            'bulk_na' => $a->bulk_na,
            'risk_level' => $a->risk_level,
            'notes' => $a->notes,
            'trial_count' => (int) ($trialCounts[$a->case_id] ?? 0),
            'created_by_name' => $a->created_by_name,
        ]);

        $option = fn (string $type) => MasterOption::query()
            ->where('type', $type)->where('is_active', 1)->whereNull('deleted_at')
            ->orderBy('sort_order')->orderBy('name')->pluck('name');

        return Inertia::render('validation/assessments/index', [
            'assessments' => [
                'data' => $rows->values(),
                'current_page' => $assessments->currentPage(),
                'last_page' => $assessments->lastPage(),
                'per_page' => $assessments->perPage(),
                'total' => $assessments->total(),
                'links' => $assessments->linkCollection()->all(),
            ],
            'filters' => $f,
            'products' => Product::query()->where('is_active', 1)->whereNull('deleted_at')
                ->orderBy('product_name')->get(['id', 'product_name', 'finish_good_code']),
            'masterOptions' => [
                'product_type' => $option('product_type'),
                'validation_scope' => $option('validation_scope'),
                'machine_used' => $option('machine_used'),
            ],
            'caseTypes' => ValidationCaseType::ALL,
            'riskLevels' => ['Low', 'Medium', 'High'],
            'can' => ['create' => $canCreate, 'delete' => $user->isAdmin()],
        ]);
    }

    public function store(SaveValidationAssessmentRequest $request, SaveValidationAssessment $action): RedirectResponse
    {
        $assessment = $action(null, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.created', ['case' => $assessment->case->case_no])]);

        return to_route('journey.show', $assessment->case_id);
    }

    public function update(SaveValidationAssessmentRequest $request, int $assessment, SaveValidationAssessment $action): RedirectResponse
    {
        $model = ValidationAssessment::query()->whereNull('deleted_at')->findOrFail($assessment);

        $action($model, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.updated')]);

        return back();
    }

    /** Admin only; only while no trial is linked to the Case (its number stays issued). */
    public function destroy(Request $request, int $assessment): RedirectResponse
    {
        $model = ValidationAssessment::query()->whereNull('deleted_at')->findOrFail($assessment);
        Gate::authorize('delete', $model);

        $linked = ValidationCaseLink::query()->where('case_id', $model->case_id)->exists();
        if ($linked) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('assessment.toast.delete_blocked')]);

            return back();
        }

        DB::transaction(function () use ($model, $request) {
            $now = Carbon::now();
            $model->deleted_at = $now;
            $model->save();

            $case = $model->case;
            $case->deleted_at = $now;
            $case->save();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'user_role' => $request->user()->role,
                'action' => 'DELETE',
                'module' => 'VALIDATION_ASSESSMENT',
                'record_id' => (string) $model->id,
                'record_label' => $case->case_no,
                'old_data' => null,
                'new_data' => null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.deleted')]);

        return back();
    }
}
