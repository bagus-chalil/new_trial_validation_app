<?php

namespace App\Policies;

use App\Models\Mixing;
use App\Models\User;
use App\Support\SelfApprovalGuard;

/**
 * Mixing authorization (Tahap 4), the same shape as the Trial rules but
 * simpler: a Draft is visible to its owner and Admin only; once submitted it
 * is readable by every signed-in user. Only Staff create; the owner (or Admin)
 * edits while Draft / Need Revision; the assigned approver (or Admin) decides.
 */
class MixingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mixing $mixing): bool
    {
        if ($mixing->progress_status === 'Draft') {
            return $user->isAdmin() || $mixing->isOwner($user);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Mixing $mixing): bool
    {
        if (! in_array($mixing->progress_status, ['Draft', 'Need Revision'], true)) {
            return false;
        }

        return $user->isStaff() && ($user->isAdmin() || $mixing->isOwner($user));
    }

    public function delete(User $user, Mixing $mixing): bool
    {
        return $mixing->progress_status === 'Draft' && ($user->isAdmin() || $mixing->isOwner($user));
    }

    public function approve(User $user, Mixing $mixing): bool
    {
        if ($mixing->progress_status !== 'Ready for Approval') {
            return false;
        }

        if (SelfApprovalGuard::isPreparerByEmail($user, $mixing->created_by)) {
            return false;
        }

        return $user->isAdmin() || (int) $mixing->approver_user_id === $user->id;
    }
}
