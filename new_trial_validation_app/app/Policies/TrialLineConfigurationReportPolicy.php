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
 * elsewhere.
 */
class TrialLineConfigurationReportPolicy
{
    public function approvePie(User $user, TrialLineConfigurationReport $report): bool
    {
        if ($report->approved_pie) {
            return false;
        }

        return $user->isAdmin() || (! empty($report->approved_pie_user_id) && (int) $report->approved_pie_user_id === $user->id);
    }

    public function checkProd(User $user, TrialLineConfigurationReport $report): bool
    {
        if (! $report->approved_pie || $report->checked_prod) {
            return false;
        }

        return $user->isAdmin() || (! empty($report->checked_prod_user_id) && (int) $report->checked_prod_user_id === $user->id);
    }

    public function returnReport(User $user, TrialLineConfigurationReport $report): bool
    {
        $stageUserId = $report->currentStageUserId();

        return $user->isAdmin() || ($stageUserId !== null && $stageUserId === $user->id);
    }
}
