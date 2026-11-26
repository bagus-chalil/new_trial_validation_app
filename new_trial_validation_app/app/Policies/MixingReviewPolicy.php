<?php

namespace App\Policies;

use App\Models\MixingReview;
use App\Models\User;
use App\Support\SelfApprovalGuard;

/**
 * A reviewer may save a Mixing review only for their own department, only
 * while it is still Pending, the Mixing is In Review and the round is current.
 */
class MixingReviewPolicy
{
    public function review(User $user, MixingReview $review): bool
    {
        if (! $user->isReviewer() || $review->status !== 'Pending') {
            return false;
        }

        $mixing = $review->mixing;
        if ($mixing === null || $mixing->deleted_at !== null || $mixing->progress_status !== 'In Review') {
            return false;
        }

        if ((int) $review->review_round !== $mixing->currentReviewRound()) {
            return false;
        }

        if (SelfApprovalGuard::isPreparerByEmail($user, $mixing->created_by)) {
            return false;
        }

        return in_array(User::normalizeReviewDepartment($review->department), $user->reviewDepartmentsForUser(), true);
    }
}
