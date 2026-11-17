<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveProjectStage
{
    public function __construct(
        private readonly LogProjectActivity $log,
        private readonly RecalculateProjectProgress $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Project $project, ?ProjectStage $stage, array $data, User $user): ProjectStage
    {
        return DB::transaction(function () use ($project, $stage, $data, $user) {
            $name = trim($data['name']);
            $status = $data['status'];
            $progress = (int) $data['progress'];

            // Keep status and progress coherent: Completed means 100%, and a
            // 100% stage is Completed.
            if ($status === 'Completed') {
                $progress = 100;
            } elseif ($progress === 100) {
                $status = 'Completed';
            }

            $type = $data['stage_type'] ?? null;
            $type ??= preg_match('/valid|trial/i', $name) === 1 ? 'Validation' : 'General';

            $attributes = [
                'name' => $name,
                'stage_type' => $type,
                'pic_name' => trim((string) ($data['pic_name'] ?? '')),
                'plan_start' => $data['plan_start'] ?? null,
                'plan_end' => $data['plan_end'] ?? null,
                'actual_start' => $data['actual_start'] ?? null,
                'actual_end' => $data['actual_end'] ?? null,
                'progress' => $progress,
                'is_parallel' => (bool) ($data['is_parallel'] ?? false),
                'status' => $status,
                'note' => $data['note'] ?? null,
            ];

            if (! $stage) {
                $stage = new ProjectStage;
                $attributes['project_id'] = $project->id;
                $attributes['sort_order'] = (int) $project->stages()->max('sort_order') + 1;
                $stage->fill($attributes)->save();
                ($this->log)($user, 'CREATE_STAGE', $project->id, $project->name, null, ['stage' => $name]);
            } else {
                $old = $stage->only(['name', 'status', 'progress', 'plan_start', 'plan_end']);
                $stage->fill($attributes)->save();
                ($this->log)($user, 'UPDATE_STAGE', $project->id, $project->name, $old, $stage->only(array_keys($old)));
            }

            ($this->recalculate)($project);

            return $stage;
        });
    }
}
