<?php

namespace App\Policies;

use App\Models\TrialLineConfigurationReport;
use App\Models\User;

/**
 * Authorizes the maker-checker actions on a Line Configuration Report's
 * *current* version — deliberately separate from the
 * `manage-line-configuration-report` Gate (PROD reviewer/Admin), which only
 * governs general form editing. The assigned Approved(PIE)/Checked(PROD)
 * user is very often someone with no PROD review team membership at all
 * (e.g. a PIE-department person), so each ability checks the specific
 * assignment on the report itself instead.
 *
 * approvePie()/checkProd()/returnReport() together enforce the sequential
 * chain (see TrialLineConfigurationReport::currentApprovalStage()):
 * Checked(PROD) can't be confirmed before Approved(PIE) is, and Return is
 * only available to whichever stage is currently active — never to a
 * "reviewer" whose turn hasn't come up yet, and not to the trial's own
 * reviewers/approvers just because they happen to be acting on the trial
 * elsewhere. All three also require the current stage to actually have an
 * assignee (report submitted into the chain) — nobody, Admin included, can
 * approve/check/return a report nobody has been assigned to yet, e.g. right
 * after a Return reset every assignment back to unset.
 *
 * Deliberately **no Admin bypass** on any of these three, unlike the
 * `manage-line-configuration-report` edit-lock override (which does let an
 * Admin edit regardless of assignment/lock state) — these are personal
 * sign-off actions mirroring a real signature, stamped with the acting
 * user's own name (see MarkTrialLineConfigurationReportSignOff/
 * ReturnTrialLineConfigurationReport). Letting an Admin click Approve/
 * Checked/Return on behalf of whoever the report actually names would let
 * one account silently produce someone else's signature — an Admin who
 * genuinely needs to act on a stage must be assigned to it by user id, the
 * same as anyone else.
 */
class TrialLineConfigurationReportPolicy
{
    public function approvePie(User $user, TrialLineConfigurationReport $report): bool
    {
        if ($report->approved_pie || empty($report->approved_pie_user_id)) {
            return false;
        }

        return (int) $report->approved_pie_user_id === $user->id;
    }

    public function checkProd(User $user, TrialLineConfigurationReport $report): bool
    {
        if (! $report->approved_pie || $report->checked_prod || empty($report->checked_prod_user_id)) {
            return false;
        }

        return (int) $report->checked_prod_user_id === $user->id;
    }

    public function returnReport(User $user, TrialLineConfigurationReport $report): bool
    {
        $stageUserId = $report->currentStageUserId();

        return $stageUserId !== null && $stageUserId === $user->id;
    }
}
