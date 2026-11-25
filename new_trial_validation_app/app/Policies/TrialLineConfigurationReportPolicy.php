<?php

namespace App\Policies;

use App\Models\LineConfigurationLane;
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
 * Approved(PIE Leader) can't be confirmed before Checked(PROD) is, and Return is
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
    /**
     * PIE Leader approval — only after PROD has acknowledged (order reversed
     * 2026-11-25), by any user the 'approved_pie' lane lets sign. Nobody is
     * assigned on the form; a report that was never submitted into the chain
     * can't be approved by anyone.
     */
    public function approvePie(User $user, TrialLineConfigurationReport $report): bool
    {
        if (! $report->isSubmittedForApproval() || ! $report->checked_prod || $report->approved_pie) {
            return false;
        }

        return LineConfigurationLane::userMaySign($user, 'approved_pie');
    }

    /** PROD acknowledgement — always the first stage of a submitted report. */
    public function checkProd(User $user, TrialLineConfigurationReport $report): bool
    {
        if (! $report->isSubmittedForApproval() || $report->checked_prod) {
            return false;
        }

        return LineConfigurationLane::userMaySign($user, 'checked_prod');
    }

    public function returnReport(User $user, TrialLineConfigurationReport $report): bool
    {
        return $report->isSubmittedForApproval() && $report->canBeSignedBy($user);
    }
}
