<?php

namespace App\Http\Controllers;

use App\Actions\Trials\CheckTrialCompleteness;
use App\Actions\Trials\SubmitTrialForReview;
use App\Http\Requests\Trials\SubmitTrialForReviewRequest;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wizard Step 6 (Review & Submit) — the trial owner's side of legacy's
 * Department Review workflow. Legacy has no dedicated page for this: the
 * "Submit for Review" button + department/approver picker live inside the
 * Report page (app/views/report.php, public/index.php:740-793), which
 * itself is a separate, still-deferred Fase 3 sub-item (Reports). This
 * controller ports just the submit-for-review action and a completeness/
 * status view for it, leaving the full print-style report for later.
 *
 * The reviewer's own side (the /reviews department inbox and /review/{id}/save
 * action) is a separate, non-wizard feature — see App\Http\Controllers\ReviewController.
 */
class TrialReviewController extends Controller
{
    public function edit(int $trial): Response
    {
        $trial = Trial::whereNull('deleted_at')->withCount('attachments')->findOrFail($trial);

        Gate::authorize('view', $trial);

        $reviews = $trial->reviews()->with('reviewer:id,name,email')->orderBy('review_round')->orderBy('department')->get();

        $approversQuery = User::query()
            ->where('is_active', 1)
            ->whereNull('deleted_at');
        User::applyEffectiveRoleIn($approversQuery, User::approverEligibleRoles());
        $approvers = $approversQuery
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'app_role']);

        $reviewerDepartments = User::reviewerDepartmentCodes();

        $reviewersByDepartment = [];
        foreach ($reviewerDepartments as $department) {
            $reviewersByDepartment[$department] = User::query()
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(review_unit)) = ?', [$department])
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'label' => trim((string) ($u->name ?: $u->email)),
                ])
                ->values();
        }

        return Inertia::render('trials/review', [
            'trial' => $trial,
            'reviewerDepartments' => $reviewerDepartments,
            'reviewersByDepartment' => $reviewersByDepartment,
            'reviews' => $reviews->map(fn (TrialReview $r) => [
                'department' => $r->department,
                'review_round' => $r->review_round,
                'status' => $r->status,
                'reviewer_name' => $r->reviewer_name,
                'assigned_to' => $r->reviewer?->name,
                'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                'comment' => $r->comment,
            ]),
            'approvers' => $approvers->map(fn (User $u) => [
                'id' => $u->id,
                'label' => trim((string) ($u->name ?: $u->email)).' - '.$u->effectiveRole(),
            ]),
            'selectedApproverId' => $trial->approver_user_id,
            'completeness' => (new CheckTrialCompleteness)($trial),
            'canEdit' => Gate::allows('submitForReview', $trial),
        ]);
    }

    public function store(SubmitTrialForReviewRequest $request, int $trial, SubmitTrialForReview $action): RedirectResponse
    {
        $trial = Trial::whereNull('deleted_at')->findOrFail($trial);
        $approverQuery = User::where('is_active', 1)->whereNull('deleted_at');
        User::applyEffectiveRoleIn($approverQuery, User::approverEligibleRoles());
        $approver = $approverQuery->findOrFail($request->integer('approver_user_id'));

        $action($trial, $request->departments(), $request->reviewerUserIds(), $approver, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trial berhasil dikirim untuk review.']);

        return to_route('trials.report.show', $trial);
    }
}
