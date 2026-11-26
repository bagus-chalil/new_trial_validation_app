<?php

namespace App\Http\Controllers;

use App\Actions\Validation\SaveCaseClassification;
use App\Actions\Validation\VerifyFgMapping;
use App\Models\FgMapping;
use App\Models\ValidationCase;
use App\Services\Validation\ValidationRoles;
use App\Support\CaseClassification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Tahap 5 alignment actions on a Case: the classification axes (brief 02) and
 * confirming the verified FG mapping (brief 03). PIE or Staff classify; PIE or
 * QA verify a mapping.
 */
class ValidationCaseAlignmentController extends Controller
{
    public function classify(Request $request, int $case, SaveCaseClassification $save): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->findOrFail($case);
        $user = $request->user();
        abort_unless(ValidationRoles::isPie($user) || $user->isStaff(), 403);

        $data = $request->validate([
            'process_area' => ['required', Rule::in(CaseClassification::PROCESS_AREAS)],
            'purpose' => ['required', Rule::in(CaseClassification::PURPOSES)],
            'subject' => ['required', Rule::in(CaseClassification::SUBJECTS)],
            'document_type' => ['nullable', Rule::in(CaseClassification::DOCUMENT_TYPES)],
            'other_purpose_note' => ['nullable', 'string', 'max:255', 'required_if:purpose,Other'],
        ]);

        $save($case, $data, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('journey.toast.classification_saved')]);

        return back();
    }

    public function verifyFg(Request $request, int $case, VerifyFgMapping $verify): RedirectResponse
    {
        $case = ValidationCase::query()->whereNull('deleted_at')->with('product')->findOrFail($case);
        $user = $request->user();
        abort_unless(ValidationRoles::isPie($user) || ValidationRoles::isQa($user), 403);

        $product = $case->product;
        $code = $product === null ? '' : trim((string) $product->finish_good_code);
        $mapping = $code === '' ? null : FgMapping::query()->where('fg_code', $code)->first();

        // Only a code iERP actually listed, and not already pinned to another product.
        abort_if($product === null || $mapping === null, 422, __('journey.fg.cannot_verify'));
        abort_if($mapping->product_id !== null && (int) $mapping->product_id !== (int) $product->id, 422, __('journey.fg.cannot_verify'));

        $verify($mapping, $product, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('journey.toast.fg_verified')]);

        return back();
    }
}
