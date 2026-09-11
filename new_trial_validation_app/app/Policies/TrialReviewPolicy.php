<?php

namespace App\Policies;

use App\Models\TrialReview;
use App\Models\User;

/**
 * Port of the authorization checks inline in the legacy app's public/index.php
 * for /reviews (die('Reviewer only')) and /review/{id}/save (department match
 * + "is this review still the active one" check, public/index.php:796-818).
 *
 * Beyond the legacy port: a reviewer may also revise their own already-
 * submitted ('Reviewed') comment, up to TrialReview::MAX_EDITS times, for as
 * long as the trial hasn't reached a final approval decision yet
 * ($trial->final_decision still null — set by SaveApprovalDecision on
 * Approved/Need Revision/Rejected, not just once the trial reaches
 * "Ready for Approval"/waiting on the approver).
 */
class TrialReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReviewer();
    }

    public function update(User $user, TrialReview $review): bool
    {
        if (! $user->isReviewer()) {
            return false;
        }

        if (! in_array(User::normalizeDepartment($review->department), $user->reviewDepartmentsForUser(), true)) {
            return false;
        }

        $trial = $review->trial;

        if (! $trial || $trial->final_decision !== null) {
            return false;
        }

        if ((int) $review->review_round !== $trial->currentReviewRound()) {
            return false;
        }

        if ($review->status === 'Pending') {
            return $trial->progress_status === 'In Review';
        }

        if ($review->status === 'Reviewed') {
            return in_array($trial->progress_status, ['In Review', 'Ready for Approval'], true)
                && $review->edit_count < TrialReview::MAX_EDITS;
        }

        return false;
    }
}
