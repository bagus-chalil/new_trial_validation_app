<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ValidationPlan;
use App\Services\Validation\ValidationRoles;

/**
 * Weekly trial schedule requests: PIE asks, PPIC confirms. Everybody reads.
 */
class ValidationPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ValidationRoles::isPie($user);
    }

    /** PPIC confirms (or reschedules by confirming with a note). */
    public function confirm(User $user, ValidationPlan $plan): bool
    {
        return ValidationRoles::isPpic($user);
    }

    /** The requester, or Admin, withdraws a request. */
    public function cancel(User $user, ValidationPlan $plan): bool
    {
        return $user->isAdmin() || ($plan->requested_by_user_id !== null && (int) $plan->requested_by_user_id === $user->id);
    }
}
