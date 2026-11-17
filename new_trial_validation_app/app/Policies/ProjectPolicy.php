<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Projects module access (PROJECT_MODULE_PLAN.md section 4): Admin/Super Admin
 * manage everything, Staff may create and change projects they are PIC of or
 * created, Viewer (and every other role) reads only.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isStaff()
            && ((int) $project->pic_user_id === $user->id || (int) $project->created_by_user_id === $user->id);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }
}
