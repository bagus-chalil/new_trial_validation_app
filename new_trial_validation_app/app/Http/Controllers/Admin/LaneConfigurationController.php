<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLineConfigurationLaneRequest;
use App\Models\LineConfigurationLane;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 3 of the RBAC/Team-master redesign (see memory
 * rbac_team_lane_redesign_2026_09_22): admin screen for the Line
 * Configuration Report's two sign-off stages ("Approved (PIE)" /
 * "Checked (PROD)") — which Team (if any) is required to be assigned to
 * each, and the stage's display label. Exactly 2 fixed rows (see the
 * `line_configuration_lanes` migration) — only their required team/label is
 * editable here, not the number of stages. Super-Admin-only, same trust
 * tier as Access Rights (reuses the `manage-access-rights` Gate).
 */
class LaneConfigurationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-access-rights');

        $lanes = LineConfigurationLane::query()->orderBy('id')->get();

        return Inertia::render('admin/lane-configuration/index', [
            'lanes' => $lanes->map(fn (LineConfigurationLane $lane) => [
                'id' => $lane->id,
                'stage_key' => $lane->stage_key,
                'label' => $lane->label,
                'required_team_id' => $lane->required_team_id,
            ])->values(),
            'reviewTeams' => User::reviewTeams()->values(),
        ]);
    }

    public function update(UpdateLineConfigurationLaneRequest $request, LineConfigurationLane $lane): RedirectResponse
    {
        $data = $request->validated();

        $lane->label = $data['label'];
        $lane->required_team_id = $data['required_team_id'] ?? null;
        $lane->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lane Configuration berhasil diperbarui.']);

        return to_route('admin.lane-configuration.index');
    }
}
