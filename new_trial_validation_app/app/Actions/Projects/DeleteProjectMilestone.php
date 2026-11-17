<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;

class DeleteProjectMilestone
{
    public function __construct(private readonly LogProjectActivity $log) {}

    public function __invoke(Project $project, ProjectMilestone $milestone, User $user): void
    {
        $name = $milestone->name;
        $milestone->delete();

        ($this->log)($user, 'DELETE_MILESTONE', $project->id, $project->name, ['milestone' => $name]);
    }
}
