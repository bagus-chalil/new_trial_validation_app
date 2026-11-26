<?php

namespace App\Http\Controllers;

use App\Actions\Validation\SaveTrialMeta;
use App\Models\ActivityLog;
use App\Models\Mixing;
use App\Models\Trial;
use App\Models\ValidationAssessment;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use App\Services\Validation\CaseJourney;
use App\Services\Validation\IpcBatchLookup;
use App\Services\Validation\ValidationRoles;
use App\Support\ValidationCaseType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trial Validation v2.0 Product Journey (minimal, Tahap 1): the Case list and
 * one page per Case with how many times it was trialled, the result of each
 * attempt, and what is linked to it. Read-only except for recording the
 * client approval evidence.
 */
class ValidationJourneyController extends Controller
{
    public function index(Request $request, CaseJourney $journey): Response
    {
        Gate::authorize('viewAny', ValidationCase::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => trim((string) $request->query('type', '')),
        ];

        $cases = ValidationCase::query()
            ->whereNull('deleted_at')
            ->with('product:id,product_name,finish_good_code')
            ->when($filters['type'] !== '' && in_array($filters['type'], ValidationCaseType::ALL, true), fn ($q) => $q->where('case_type', $filters['type']))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '%_\\').'%';
                $q->where(function ($q) use ($like) {
                    $q->where('case_no', 'like', $like)
                        ->orWhere('product_name', 'like', $like)
                        ->orWhereHas('product', fn ($p) => $p->where('product_name', 'like', $like)->orWhere('finish_good_code', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $user = $request->user();
        $rows = $cases->getCollection()->map(function (ValidationCase $case) use ($journey, $user) {
            $built = $journey->build($case, $user);

            return [
                'id' => $case->id,
                'case_no' => $case->case_no,
                'case_type' => $case->case_type,
                'product_name' => $built['case']['product_name'],
                'finish_good_code' => $built['case']['finish_good_code'],
                'total' => $built['summary']['total'],
                'counts' => $built['summary']['counts'],
                'last_result' => $built['summary']['last_result'],
            ];
        });

        return Inertia::render('journey/index', [
            'cases' => [
                'data' => $rows->values(),
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'links' => $cases->linkCollection()->all(),
            ],
            'filters' => $filters,
            'caseTypes' => ValidationCaseType::ALL,
        ]);
    }

    public function show(Request $request, int $case, CaseJourney $journey): Response
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        Gate::authorize('view', $case);

        return Inertia::render('journey/show', [
            ...$journey->build($case, $request->user()),
            ...$journey->extras($case),
            ...$journey->numbers($case, $request->user()),
            'ipc' => app(IpcBatchLookup::class)->forCase($case),
            'mixings' => $journey->mixings($case),
            'canCreateMixing' => Gate::forUser($request->user())->allows('create', Mixing::class),
            'canCreateTrial' => Gate::forUser($request->user())->allows('create', Trial::class),
            'canManageCase' => ValidationRoles::isPie($request->user()),
            'canEditAssessment' => Gate::forUser($request->user())->allows('create', ValidationAssessment::class),
            'canRecordClientApproval' => Gate::forUser($request->user())->allows('recordClientApproval', $case),
        ]);
    }

    public function clientApproval(Request $request, int $case, int $trial, SaveTrialMeta $meta): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        Gate::authorize('recordClientApproval', $case);

        // The trial must really belong to this Case.
        abort_unless(
            ValidationCaseLink::query()->where('case_id', $case->id)->where('kind', 'trial')->where('ref_key', (string) $trial)->exists(),
            404,
        );

        $trialModel = Trial::query()->whereNull('deleted_at')->findOrFail($trial);

        $data = $request->validate([
            'approved' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:approved,true,1'],
        ]);

        $user = $request->user();
        $meta->saveClientApproval($trialModel, (bool) $data['approved'], $data['note'] ?? null, $user);

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'UPDATE',
            'module' => 'VALIDATION_CASE',
            'record_id' => (string) $case->id,
            'record_label' => $case->case_no,
            'old_data' => null,
            'new_data' => json_encode(['trial' => $trialModel->trial_code, 'client_approved' => (bool) $data['approved'], 'note' => $data['note'] ?? null]),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('journey.toast.client_approval_saved')]);

        return back();
    }
}
