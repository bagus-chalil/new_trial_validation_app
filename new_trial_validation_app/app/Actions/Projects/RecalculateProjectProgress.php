<?php

namespace App\Actions\Projects;

use App\Models\Project;

/** Project progress = rounded average of its stages' progress (unchanged when there are no stages). */
class RecalculateProjectProgress
{
    public function __invoke(Project $project): void
    {
        $stages = $project->stages()->get(['progress']);

        if ($stages->isEmpty()) {
            return;
        }

        $project->progress = (int) round($stages->avg('progress'));
        $project->save();
    }
}
