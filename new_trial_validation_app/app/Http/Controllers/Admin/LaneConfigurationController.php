<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLineConfigurationLaneRequest;
use App\Models\LineConfigurationLane;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
 * editable here, not the number of stages. A lane can also name explicit
 * signer users (the PIE Leaders who approve), which win over its team. Super-Admin-only, same trust
 * tier as Access Rights (reuses the `manage-access-rights` Gate).
 */
class LaneConfigurationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-access-rights');

        $lanes = LineConfigurationLane::query()->orderBy('id')->get();
        $signerIds = DB::table('line_configuration_lane_users')->get()->groupBy('lane_id');

        return Inertia::render('admin/lane-configuration/index', [
            'lanes' => $lanes->map(fn (LineConfigurationLane $lane) => [
                'id' => $lane->id,
                'stage_key' => $lane->stage_key,
                'label' => $lane->label,
                'required_team_id' => $lane->required_team_id,
                'signer_user_ids' => ($signerIds[$lane->id] ?? collect())->pluck('user_id')->map(fn ($id) => (int) $id)->values(),
            ])->values(),
            'reviewTeams' => User::reviewTeams()->values(),
            'users' => User::query()->where('is_active', 1)->whereNull('deleted_at')
                ->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => trim((string) ($u->name ?: $u->email))])
                ->values(),
        ]);
    }

    public function update(UpdateLineConfigurationLaneRequest $request, LineConfigurationLane $lane): RedirectResponse
    {
        $data = $request->validated();

        $lane->label = $data['label'];
        $lane->required_team_id = $data['required_team_id'] ?? null;
        $lane->save();

        if ($request->boolean('sync_signers')) {
            DB::transaction(function () use ($lane, $data) {
                DB::table('line_configuration_lane_users')->where('lane_id', $lane->id)->delete();
                foreach (array_unique($data['signer_user_ids'] ?? []) as $userId) {
                    DB::table('line_configuration_lane_users')->insert([
                        'lane_id' => $lane->id,
                        'user_id' => (int) $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.toast.lane_updated')]);

        return to_route('admin.lane-configuration.index');
    }
}
