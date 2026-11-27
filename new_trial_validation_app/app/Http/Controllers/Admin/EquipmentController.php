<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Machines\GenerateEquipmentCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEquipmentRequest;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\MachineLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Equipment rows of a line. The equipment code is generated once and never changes. */
class EquipmentController extends Controller
{
    public function store(StoreEquipmentRequest $request, MachineLine $line, GenerateEquipmentCode $generateCode): RedirectResponse
    {
        $data = $request->validated();
        $id = ! empty($data['id']) ? (int) $data['id'] : null;
        unset($data['id']);

        if ($id) {
            $equipment = Equipment::where('line_id', $line->id)->findOrFail($id);
            $equipment->fill($data)->save();
        } else {
            $data['line_id'] = $line->id;
            $data['equipment_code'] = $generateCode($line);
            $data['process_step'] ??= (int) Equipment::withTrashed()->where('line_id', $line->id)->max('process_step') + 1;
            $data['is_active'] = true;
            $equipment = Equipment::create($data);
        }

        $this->log($request, $id ? 'UPDATE' : 'CREATE', $equipment);
        Inertia::flash('toast', ['type' => 'success', 'message' => __($id ? 'machines.toast.eq_updated' : 'machines.toast.eq_created', ['code' => $equipment->equipment_code])]);

        return back();
    }

    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        Gate::authorize('manage-machines');

        $equipment->deleted_by = $request->user()->id;
        $equipment->is_active = false;
        $equipment->save();
        $equipment->delete();
        $this->log($request, 'DELETE', $equipment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('machines.toast.eq_deleted')]);

        return back();
    }

    private function log(Request $request, string $action, Equipment $equipment): void
    {
        $user = $request->user();
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'MACHINE',
            'record_id' => (string) $equipment->id,
            'record_label' => $equipment->equipment_code,
            'old_data' => null,
            'new_data' => null,
        ]);
    }
}
