<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;

class SaveProjectMilestone
{
    public function __construct(private readonly LogProjectActivity $log) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Project $project, ?ProjectMilestone $milestone, array $data, User $user): ProjectMilestone
    {
        $attributes = [
            'name' => trim($data['name']),
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
        ];

        if (! $milestone) {
            $milestone = new ProjectMilestone;
            $milestone->fill($attributes + ['project_id' => $project->id])->save();
            ($this->log)($user, 'CREATE_MILESTONE', $project->id, $project->name, null, ['milestone' => $milestone->name]);
        } else {
            $old = $milestone->only(['name', 'due_date', 'status']);
            $milestone->fill($attributes)->save();
            ($this->log)($user, 'UPDATE_MILESTONE', $project->id, $project->name, $old, $milestone->only(array_keys($old)));
        }

        return $milestone;
    }
}
