<?php

namespace App\Http\Controllers;

use App\Actions\Validation\LinkCaseNumber;
use App\Models\ImNumber;
use App\Models\QcoNumber;
use App\Models\ValidationCase;
use App\Services\Validation\ValidationRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Trial Validation v2.0 Tahap 3: record an issued QCO / IM number on a Case
 * from the Product Journey. QCO is linked by PRNI (or PIE, who own the Case),
 * IM by QA (or PIE); Admin does both.
 */
class ValidationCaseNumberController extends Controller
{
    public function store(Request $request, int $case, LinkCaseNumber $action): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['qco', 'im'])],
            'number_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        abort_unless(
            ValidationRoles::isPie($user) || ($data['kind'] === 'qco' ? ValidationRoles::isPrni($user) : ValidationRoles::isQa($user)),
            403
        );

        $number = $data['kind'] === 'qco'
            ? QcoNumber::query()->where('status', 'Issued')->findOrFail((int) $data['number_id'])
            : ImNumber::query()->where('status', '!=', 'Void')->findOrFail((int) $data['number_id']);

        $action->link($case, $number, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.number_linked')]);

        return back();
    }

    public function destroy(Request $request, int $case, string $kind, int $number, LinkCaseNumber $action): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        abort_unless(in_array($kind, ['qco', 'im'], true), 404);

        $user = $request->user();
        abort_unless(
            ValidationRoles::isPie($user) || ($kind === 'qco' ? ValidationRoles::isPrni($user) : ValidationRoles::isQa($user)),
            403
        );

        $model = $kind === 'qco' ? QcoNumber::query()->findOrFail($number) : ImNumber::query()->findOrFail($number);
        $action->unlink($case, $kind, $model->number, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('numbers.toast.number_unlinked')]);

        return back();
    }
}
