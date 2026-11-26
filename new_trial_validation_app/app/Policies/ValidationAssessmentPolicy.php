<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ValidationAssessment;
use App\Services\Validation\ValidationRoles;

/**
 * Validation Assessment register. Everybody logged in reads it (like the
 * Product Journey); PIE (and Admin) create and edit, because PIE decides the
 * Risk at assessment (D9).
 */
class ValidationAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ValidationAssessment $assessment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ValidationRoles::isPie($user);
    }

    public function update(User $user, ValidationAssessment $assessment): bool
    {
        return ValidationRoles::isPie($user);
    }

    public function delete(User $user, ValidationAssessment $assessment): bool
    {
        return $user->isAdmin();
    }
}
