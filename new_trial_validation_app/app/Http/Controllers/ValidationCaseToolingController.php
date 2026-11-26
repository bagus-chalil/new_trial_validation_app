<?php

namespace App\Http\Controllers;

use App\Actions\Validation\LinkCaseTooling;
use App\Models\Tooling;
use App\Models\ValidationCase;
use App\Services\Validation\ValidationRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Trial Validation v2.0 Tahap 2: tie a Check Tooling request to a Case from
 * the Product Journey. pm_toolings is never written; `npd_no` stays as the
 * old reference.
 */
class ValidationCaseToolingController extends Controller
{
    public function store(Request $request, int $case, LinkCaseTooling $action): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        abort_unless(ValidationRoles::isPie($request->user()), 403);

        $data = $request->validate([
            'tooling_id' => ['required', 'integer', Rule::exists('pm_toolings', 'id')->whereNull('deleted_at')],
        ]);

        $tooling = Tooling::query()->whereNull('deleted_at')->where('id', (int) $data['tooling_id'])->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $tooling);

        $action->link($case, $tooling, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.tooling_linked')]);

        return back();
    }

    public function destroy(Request $request, int $case, int $tooling, LinkCaseTooling $action): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        abort_unless(ValidationRoles::isPie($request->user()), 403);

        $tooling = Tooling::query()->findOrFail($tooling);
        $action->unlink($case, $tooling, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.toast.tooling_unlinked')]);

        return back();
    }
}
