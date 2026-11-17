<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Soft delete only (deleted_at/deleted_by); stages, files and quotations stay for a restore. */
class DeleteProject
{
    public function __construct(private readonly LogProjectActivity $log) {}

    public function __invoke(Project $project, User $user): void
    {
        $project->deleted_at = Carbon::now();
        $project->deleted_by = $user->id;
        $project->save();

        ($this->log)($user, 'DELETE', $project->id, $project->name);
    }
}
