<?php

namespace App\Http\Controllers;

use App\Actions\Validation\LinkCaseImprovement;
use App\Models\Improvement;
use App\Models\ValidationCase;
use App\Services\Validation\ValidationRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Trial Validation v2.0 Tahap 2: tie an Improvement to a Case from the
 * Product Journey (PIE or Admin). pm_improvements is never written.
 */
class ValidationCaseImprovementController extends Controller
{
    public function store(Request $request, int $case, LinkCaseImprovement $action): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        abort_unless(ValidationRoles::isPie($request->user()), 403);

        $data = $request->validate([
            'improvement_id' => ['required', 'integer', Rule::exists('pm_improvements', 'id')->whereNull('deleted_at')],
        ]);

        $improvement = Improvement::query()->whereNull('deleted_at')->where('id', (int) $data['improvement_id'])->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $improvement);

        $action->link($case, $improvement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.improvement_linked')]);

        return back();
    }

    public function destroy(Request $request, int $case, int $improvement, LinkCaseImprovement $action): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        abort_unless(ValidationRoles::isPie($request->user()), 403);

        $model = Improvement::query()->where('id', $improvement)->firstOrFail();
        $action->unlink($case, $model, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.improvement_unlinked')]);

        return back();
    }
}
