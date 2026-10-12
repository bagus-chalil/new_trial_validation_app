<?php

namespace App\Http\Controllers;

use App\Actions\Trials\SaveApprovalDecision;
use App\Http\Requests\Approvals\SaveApprovalDecisionRequest;
use App\Models\LineConfigurationLane;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Port of legacy's /approvals (GET) + /trials/{id}/approval (POST) —
 * Manager QAC (or a specifically-assigned approver) making the final
 * Approve/Need Revision/Reject call on a Ready-for-Approval trial
 * (public/index.php:859-981).
 */
class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-approval-queue');

        $q = trim((string) $request->query('q', ''));

        $items = Trial::query()
            ->awaitingApprovalFor($request->user())
            ->search(['q' => $q])
            ->with('approver:id,name,email')
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $user = $request->user();
        $lineConfigItems = TrialLineConfigurationReport::query()
            ->awaitingSignOffBy($user->id)
            ->whereHas('trial', fn ($t) => $t->whereNull('deleted_at'))
            ->with('trial:id,trial_code,product_name,product_type')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (TrialLineConfigurationReport $r) => [
                'trial_id' => $r->trial_id,
                'trial_code' => $r->trial?->trial_code,
                'product_name' => $r->trial?->product_name,
                'product_type' => $r->trial?->product_type,
                'stage' => $r->currentApprovalStage() === 'checked_prod'
                    ? LineConfigurationLane::label('checked_prod', 'Checked (PROD)')
                    : LineConfigurationLane::label('approved_pie', 'Approved (PIE)'),
            ])
            ->values();

        return Inertia::render('approvals/index', [
            'lineConfigItems' => $lineConfigItems,
            'items' => $items,
            'filters' => ['q' => $q],
        ]);
    }

    public function update(SaveApprovalDecisionRequest $request, int $trial, SaveApprovalDecision $action): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);

        $action(
            $trial,
            $request->decision(),
            trim((string) $request->string('approval_comment')),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.toast.approval_saved')]);

        return to_route('approvals.index');
    }
}
