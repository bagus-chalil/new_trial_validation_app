<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMachineOptionMapRequest;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\MachineLine;
use App\Models\MachineOptionMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin screen that ties each Trial `machine_used` option name to the machine
 * master. Trials and master_options are never changed; only mc_option_maps.
 */
class MachineOptionMapController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-machines');

        $usage = MachineOptionMap::usageCounts();
        $maps = MachineOptionMap::query()->get()->keyBy('option_name');
        $lines = MachineLine::query()->orderBy('line_code')->get(['id', 'line_code', 'name', 'category']);
        $equipment = Equipment::query()->orderBy('equipment_code')->get(['id', 'line_id', 'equipment_code', 'name']);
        $lineById = $lines->keyBy('id');
        $equipmentById = $equipment->keyBy('id');

        $rows = collect(MachineOptionMap::optionNames($usage))->map(function (string $name) use ($usage, $maps, $lineById, $equipmentById) {
            $map = $maps->get($name);

            return [
                'map_id' => $map?->id,
                'option_name' => $name,
                'trials' => $usage[$name] ?? 0,
                'status' => $map->status ?? 'unmapped',
                'score' => $map?->score,
                'reason' => $map?->reason,
                'line_id' => $map?->line_id,
                'line_label' => $map?->line_id && $lineById->has($map->line_id)
                    ? $lineById[$map->line_id]->line_code.' - '.$lineById[$map->line_id]->name : null,
                'equipment_id' => $map?->equipment_id,
                'equipment_label' => $map?->equipment_id && $equipmentById->has($map->equipment_id)
                    ? $equipmentById[$map->equipment_id]->equipment_code.' - '.$equipmentById[$map->equipment_id]->name : null,
            ];
        })->values();

        return Inertia::render('admin/machines/options', [
            'rows' => $rows,
            'lines' => $lines->map(fn ($l) => ['id' => $l->id, 'label' => $l->line_code.' - '.$l->name, 'category' => $l->category])->values(),
            'equipment' => $equipment->map(fn ($e) => ['id' => $e->id, 'line_id' => $e->line_id, 'label' => $e->equipment_code.' - '.$e->name])->values(),
        ]);
    }

    public function store(StoreMachineOptionMapRequest $request): RedirectResponse
    {
        $notApplicable = $request->boolean('not_applicable');

        MachineOptionMap::updateOrCreate(['option_name' => $request->string('option_name')->toString()], [
            'line_id' => $notApplicable ? null : $request->integer('line_id'),
            'equipment_id' => $notApplicable || ! $request->filled('equipment_id') ? null : $request->integer('equipment_id'),
            'status' => $notApplicable ? MachineOptionMap::NOT_APPLICABLE : MachineOptionMap::MANUAL,
            'score' => null,
            'reason' => null,
            'mapped_by' => $request->user()->id,
        ]);

        $this->log($request, 'UPDATE', $request->string('option_name')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('machines.mapping.toast.saved')]);

        return back();
    }

    public function destroy(Request $request, MachineOptionMap $map): RedirectResponse
    {
        Gate::authorize('manage-machines');

        $name = $map->option_name;
        $map->delete();
        $this->log($request, 'DELETE', $name);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('machines.mapping.toast.reset')]);

        return back();
    }

    private function log(Request $request, string $action, string $label): void
    {
        $user = $request->user();
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'MACHINE',
            'record_id' => 'option-map',
            'record_label' => $label,
            'old_data' => null,
            'new_data' => null,
        ]);
    }
}
