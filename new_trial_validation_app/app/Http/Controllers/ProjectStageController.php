<?php

namespace App\Http\Controllers;

use App\Actions\Projects\DeleteProjectStage;
use App\Actions\Projects\SaveProjectStage;
use App\Http\Requests\Projects\SaveProjectStageRequest;
use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectStageController extends Controller
{
    public function store(SaveProjectStageRequest $request, int $project, SaveProjectStage $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        $action($project, null, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.stage_saved')]);

        return to_route('projects.show', $project);
    }

    public function update(SaveProjectStageRequest $request, int $project, int $stage, SaveProjectStage $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $stage = ProjectStage::where('project_id', $project->id)->findOrFail($stage);

        $action($project, $stage, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.stage_saved')]);

        return to_route('projects.show', $project);
    }

    public function destroy(Request $request, int $project, int $stage, DeleteProjectStage $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $stage = ProjectStage::where('project_id', $project->id)->findOrFail($stage);

        Gate::authorize('update', $project);

        $action($project, $stage, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.stage_deleted')]);

        return to_route('projects.show', $project);
    }
}
