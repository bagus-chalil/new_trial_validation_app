<?php

namespace App\Services\Validation;

use App\Models\User;
use App\Services\Projects\ToolingWorkflow;

/**
 * Who acts in the v2.0 validation flow. Roles come from the account's
 * department / review unit (same idea as ToolingWorkflow::hasRole); no new
 * accounts or role strings. Everybody logged in may read.
 */
final class ValidationRoles
{
    /** PIE assesses (sets Risk, D9) and asks PPIC for trial weeks. */
    public static function isPie(User $user): bool
    {
        return $user->isAdmin() || ToolingWorkflow::hasRole($user, 'pi');
    }

    /** PPIC sees the weekly requests and confirms them. */
    public static function isPpic(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        foreach ([$user->department, $user->review_unit] as $value) {
            if (strtoupper(trim((string) $value)) === 'PPIC') {
                return true;
            }
        }

        return false;
    }
}
