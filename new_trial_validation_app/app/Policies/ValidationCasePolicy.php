<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ValidationCase;

/**
 * Product Journey access. Like the "Seluruh Trial" monitor, the journey is
 * readable by every logged-in user; links into the underlying trials are
 * still gated per trial by TrialPolicy::view().
 */
class ValidationCasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ValidationCase $case): bool
    {
        return true;
    }

    /** Record or clear the client approval evidence behind "Pass with Approval Client". */
    public function recordClientApproval(User $user, ValidationCase $case): bool
    {
        return $user->isStaff() || $user->canApproveTrials();
    }
}
