<?php

namespace App\Policies;

use App\Models\User;

/**
 * Port of the admin-only authorization checks around /admin/users in the
 * legacy app's public/index.php: is_admin() gates the module, with an extra
 * Super-Admin-only guard on creating/editing/deleting a Super Admin account.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return $target->effectiveRole() !== 'Super Admin' || $user->isSuperAdmin();
    }

    /**
     * "Login as User" for debugging (see Admin\ImpersonationController) —
     * same Super-Admin-target guard as update/delete, and only an active,
     * non-deleted account other than the admin's own.
     */
    public function impersonate(User $user, User $target): bool
    {
        if (! $user->isAdmin() || $user->id === $target->id) {
            return false;
        }

        if (! $target->is_active || $target->deleted_at !== null) {
            return false;
        }

        return $target->effectiveRole() !== 'Super Admin' || $user->isSuperAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->isAdmin() || $user->id === $target->id) {
            return false;
        }

        return $target->effectiveRole() !== 'Super Admin' || $user->isSuperAdmin();
    }
}
