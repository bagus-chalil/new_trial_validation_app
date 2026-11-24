<?php

namespace App\Http\Controllers;

use App\Actions\Projects\LinkProjectStageTrial;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Trial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Projects module Fase 4: link an existing Trial to a Validation stage, or
 * remove the link. Creating a brand-new draft trial from a stage goes through
 * the normal trial form instead (TrialController::create/store with
 * `project_stage`), so every trial validation rule still applies.
 */
class ProjectStageTrialController extends Controller
{
    public function store(Request $request, int $project, int $stage, LinkProjectStageTrial $action): RedirectResponse
    {
        [$project, $stage] = $this->resolve($project, $stage);
        Gate::authorize('update', $project);

        $data = $request->validate(['trial_id' => ['required', 'integer']], [], ['trial_id' => __('projects.trial_link.trial')]);

        $trial = Trial::query()->whereNull('deleted_at')->where('id', $data['trial_id'])->first();
        if (! $trial || ! $request->user()->can('view', $trial)) {
            throw ValidationException::withMessages(['trial_id' => __('projects.trial_link.trial_not_found')]);
        }

        $action->link($project, $stage, $trial, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.trial_linked')]);

        return to_route('projects.show', $project);
    }

    public function destroy(Request $request, int $project, int $stage, LinkProjectStageTrial $action): RedirectResponse
    {
        [$project, $stage] = $this->resolve($project, $stage);
        Gate::authorize('update', $project);

        $action->unlink($project, $stage, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.trial_unlinked')]);

        return to_route('projects.show', $project);
    }

    /**
     * @return array{0: Project, 1: ProjectStage}
     */
    private function resolve(int $project, int $stage): array
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $stage = ProjectStage::where('project_id', $project->id)->findOrFail($stage);

        return [$project, $stage];
    }
}
