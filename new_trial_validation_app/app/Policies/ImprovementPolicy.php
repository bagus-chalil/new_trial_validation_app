<?php

namespace App\Policies;

use App\Models\Improvement;
use App\Models\User;

/**
 * Improvement board access. Default for open decisions K1/K2 in
 * PROJECT_MODULE_PLAN.md: every logged-in user except Viewer may submit an
 * improvement and sees/edits only their own; Admin/Staff see all; the
 * reviewer is department PI/PIE (or Admin).
 */
class ImprovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Improvement $improvement): bool
    {
        return $user->isStaff() || $this->isOwner($user, $improvement) || $this->isPiReviewer($user);
    }

    public function create(User $user): bool
    {
        return ! $user->isViewer();
    }

    /** The submitter may edit their own improvement's content until PI has picked it up. */
    public function update(User $user, Improvement $improvement): bool
    {
        return $this->isOwner($user, $improvement) && $improvement->status === 'Baru';
    }

    /** Set status/decision/PI feedback, convert to a project. */
    public function review(User $user, Improvement $improvement): bool
    {
        return $user->isAdmin() || $this->isPiReviewer($user);
    }

    public function delete(User $user, Improvement $improvement): bool
    {
        return $user->isAdmin();
    }

    private function isOwner(User $user, Improvement $improvement): bool
    {
        return $improvement->submitted_by_user_id !== null
            && (int) $improvement->submitted_by_user_id === $user->id;
    }

    private function isPiReviewer(User $user): bool
    {
        foreach ([$user->department, $user->review_unit] as $value) {
            if (in_array(strtoupper(trim((string) $value)), ['PI', 'PIE'], true)) {
                return true;
            }
        }

        return false;
    }
}
