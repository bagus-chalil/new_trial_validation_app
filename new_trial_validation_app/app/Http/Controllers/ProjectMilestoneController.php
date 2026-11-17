<?php

namespace App\Http\Controllers;

use App\Actions\Projects\DeleteProjectMilestone;
use App\Actions\Projects\SaveProjectMilestone;
use App\Http\Requests\Projects\SaveProjectMilestoneRequest;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectMilestoneController extends Controller
{
    public function store(SaveProjectMilestoneRequest $request, int $project, SaveProjectMilestone $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        $action($project, null, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.milestone_saved')]);

        return to_route('projects.show', $project);
    }

    public function update(SaveProjectMilestoneRequest $request, int $project, int $milestone, SaveProjectMilestone $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $milestone = ProjectMilestone::where('project_id', $project->id)->findOrFail($milestone);

        $action($project, $milestone, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.milestone_saved')]);

        return to_route('projects.show', $project);
    }

    public function destroy(Request $request, int $project, int $milestone, DeleteProjectMilestone $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $milestone = ProjectMilestone::where('project_id', $project->id)->findOrFail($milestone);

        Gate::authorize('update', $project);

        $action($project, $milestone, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.milestone_deleted')]);

        return to_route('projects.show', $project);
    }
}
