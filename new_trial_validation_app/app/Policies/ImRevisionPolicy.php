<?php

namespace App\Policies;

use App\Models\ImRevision;
use App\Models\ImSignoff;
use App\Models\User;
use App\Services\Validation\ValidationRoles;
use App\Support\SelfApprovalGuard;

/**
 * IM authorization (Tahap 6). The whole IM chain belongs to QA: only QA (or
 * Admin) creates, edits, reviews and approves. An Effective or Superseded IM is
 * readable by every signed-in user (QC executes against it); everything else
 * (Draft, in review, Scheduled, rejected) only by QA. Nobody reviews or
 * approves a revision they prepared (SelfApprovalGuard, Admin included).
 */
class ImRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ImRevision $revision): bool
    {
        if (in_array($revision->status, ['Effective', 'Superseded'], true)) {
            return true;
        }

        return ValidationRoles::isQa($user);
    }

    public function create(User $user): bool
    {
        return ValidationRoles::isQaEditor($user);
    }

    public function update(User $user, ImRevision $revision): bool
    {
        return $revision->isEditable() && ValidationRoles::isQaEditor($user);
    }

    public function delete(User $user, ImRevision $revision): bool
    {
        return $revision->status === 'Draft' && ValidationRoles::isQaEditor($user);
    }

    public function approve(User $user, ImRevision $revision): bool
    {
        if ($revision->status !== 'Ready for Approval') {
            return false;
        }

        if (SelfApprovalGuard::isPreparerById($user, $revision->prepared_by_user_id)) {
            return false;
        }

        return ValidationRoles::isQaEditor($user) && (int) $revision->approver_user_id === $user->id;
    }

    /** Ability checked against the sign-off row (policy for ImSignoff is this class via Gate::define). */
    public function review(User $user, ImSignoff $signoff): bool
    {
        $revision = $signoff->revision;
        if ($revision === null || $revision->status !== 'In Review') {
            return false;
        }

        if ($signoff->stage !== 'review' || $signoff->status !== 'Pending' || $signoff->round !== $revision->review_round) {
            return false;
        }

        if (SelfApprovalGuard::isPreparerById($user, $revision->prepared_by_user_id)) {
            return false;
        }

        return ValidationRoles::isQaEditor($user) && (int) $signoff->user_id === $user->id;
    }
}
