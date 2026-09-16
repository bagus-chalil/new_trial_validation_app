<?php

namespace App\Policies;

use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Port of the trial-related authorization checks in the legacy app's
 * app/bootstrap.php: can_view_trial(), can_edit(), can_approve_trial().
 * Trial::scopeVisibleTo() is the list-level counterpart (scoped_trials_parts()).
 */
class TrialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trial $trial): bool
    {
        // A user assigned as the Line Configuration Report's Approved(PIE)/
        // Checked(PROD) sign-off (see MarkTrialLineConfigurationReportSignOff)
        // needs to open this trial's Report Summary to act on it, regardless
        // of status/role — they may be a PIE-department user with none of
        // the ordinary staff/reviewer/approver access below.
        $lineConfigurationReport = $trial->lineConfigurationReport;
        if ($lineConfigurationReport && (
            ($lineConfigurationReport->approved_pie_user_id && (int) $lineConfigurationReport->approved_pie_user_id === $user->id)
            || ($lineConfigurationReport->checked_prod_user_id && (int) $lineConfigurationReport->checked_prod_user_id === $user->id)
        )) {
            return true;
        }

        if ($trial->progress_status === 'Draft') {
            return $user->isSuperAdmin()
                || $user->isTrialOwner($trial)
                || $user->hasTrialEditPermission($trial->id);
        }

        if ($user->isViewer()) {
            return true;
        }

        $approverRoles = ['Manager QAC', 'Team Leader', 'Part Leader', 'Team Leader QA'];
        if ($user->isStaff() || in_array($user->role, $approverRoles, true)) {
            return true;
        }

        if ($user->isReviewer()) {
            $departments = $user->reviewDepartmentsForUser();
            $round = $trial->currentReviewRound();

            return TrialReview::query()
                ->where('trial_id', $trial->id)
                ->whereIn(DB::raw('UPPER(TRIM(department))'), $departments)
                ->when(
                    in_array($trial->progress_status, ['In Review', 'Ready for Approval'], true),
                    fn ($q) => $q->where('review_round', $round)
                )
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * In Review is a deliberate deviation from legacy's can_edit(), which
     * never allows editing past Draft/Need Revision at all — added per direct
     * user request so an owner can fix a mistake before anyone has acted on
     * it. Narrower than Draft's window: only while every department review
     * for the current round is still Pending (Trial::currentRoundReviewStarted()),
     * so an edit can never silently invalidate a review already submitted
     * against older data. Once any department has reviewed, this locks again
     * until Need Revision or Approval, same as legacy.
     */
    public function update(User $user, Trial $trial): bool
    {
        if (! $user->isStaff() || ! in_array($trial->progress_status, ['Draft', 'Need Revision', 'In Review'], true)) {
            return false;
        }

        if ($trial->progress_status === 'In Review' && $trial->currentRoundReviewStarted()) {
            return false;
        }

        if (in_array($trial->progress_status, ['Draft', 'In Review'], true)) {
            return $user->isTrialOwner($trial) || $user->hasTrialEditPermission($trial->id);
        }

        return true;
    }

    /**
     * Wizard Step 6 (Review & Submit) specifically — narrower than update()'s
     * general edit window. update() deliberately still allows fixing the
     * trial's own data (header/validation/weighing/attachments) while In
     * Review and unreviewed, but re-running the *submit-for-review* action
     * itself (department/approver selection) only ever makes sense before
     * the trial has been sent for review the first time, or after it's been
     * kicked back to Need Revision — never while it's already In Review,
     * since that would let someone silently reassign departments/approver
     * out from under a review that's already active.
     */
    public function submitForReview(User $user, Trial $trial): bool
    {
        if (! in_array($trial->progress_status, ['Draft', 'Need Revision'], true)) {
            return false;
        }

        return $this->update($user, $trial);
    }

    /**
     * Port of the authorization checks inline in legacy's POST
     * /trials/{id}/approval handler (public/index.php:884-898). Note this is
     * narrower than the approval *queue's* visibility (see
     * Trial::scopeAwaitingApprovalFor()): Team Leader/Part Leader/Team Leader
     * QA can *see* every Ready-for-Approval trial in the queue, but — same as
     * every other non-Admin/Manager-QAC role — can only actually approve one
     * that's specifically assigned to them via approver_user_id.
     */
    public function approve(User $user, Trial $trial): bool
    {
        if ($user->isAdmin() || $user->isManagerQac()) {
            return true;
        }

        if (! empty($trial->approver_user_id)) {
            return (int) $trial->approver_user_id === $user->id;
        }

        return false;
    }

    public function delete(User $user, Trial $trial): bool
    {
        return $user->isAdmin();
    }
}
