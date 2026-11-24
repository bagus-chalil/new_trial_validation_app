<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteProjectStage
{
    public function __construct(
        private readonly LogProjectActivity $log,
        private readonly DeleteProjectFile $deleteFile,
        private readonly RecalculateProjectProgress $recalculate,
    ) {}

    public function __invoke(Project $project, ProjectStage $stage, User $user): void
    {
        DB::transaction(function () use ($project, $stage, $user) {
            // Files hanging off the stage go with it (row + physical file).
            ProjectFile::where('stage_id', $stage->id)->get()
                ->each(fn (ProjectFile $file) => ($this->deleteFile)($project, $file, $user, log: false));

            $name = $stage->name;
            $stage->delete();

            ($this->log)($user, 'DELETE_STAGE', $project->id, $project->name, ['stage' => $name]);
            ($this->recalculate)($project);
        });
    }
}
