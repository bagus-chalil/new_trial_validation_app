<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Machines\GenerateLineCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMachineLineRequest;
use App\Http\Requests\Admin\StorePlantRequest;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\MachineLine;
use App\Models\Plant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Machine master (Cosmax Line Specification Database 2026): lines + plants
 * here, equipment in EquipmentController. Staff/Admin via `manage-machines`.
 * The line code is kept on update so IPC's reference never breaks.
 */
class MachineLineController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-machines');

        $q = trim((string) $request->query('q', ''));
        $lines = MachineLine::query()
            ->with('plant:id,code,name')
            ->withCount('equipment')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn ($w) => $w->where('line_code', 'like', $like)->orWhere('name', 'like', $like)
                    ->orWhere('line_type', 'like', $like)->orWhere('area', 'like', $like));
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->query('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('plant_id'), fn ($query) => $query->where('plant_id', (int) $request->query('plant_id')))
            ->orderBy('category')->orderBy('line_code')
            ->paginate(20)->withQueryString();

        return Inertia::render('admin/machines/index', [
            'lines' => $lines,
            'plants' => Plant::query()->orderBy('code')->get(['id', 'code', 'name', 'address', 'is_active']),
            'options' => $this->options(),
            'filters' => $request->only('q', 'category', 'status', 'plant_id'),
        ]);
    }

    public function show(MachineLine $line): Response
    {
        Gate::authorize('manage-machines');

        return Inertia::render('admin/machines/show', [
            'line' => $line->load('plant:id,code,name'),
            'equipment' => $line->equipment()->get(),
            'options' => $this->options(),
        ]);
    }

    public function store(StoreMachineLineRequest $request, GenerateLineCode $generateCode): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $id = ! empty($data['id']) ? (int) $data['id'] : null;
        unset($data['id']);

        if ($id) {
            $line = MachineLine::findOrFail($id);
            unset($data['line_code']); // trace key never changes after creation
            $line->fill($data)->save();
        } else {
            $data['line_code'] = strtoupper($data['line_code'] ?? '') ?: $generateCode($data['category']);
            $line = MachineLine::create($data);
        }

        $this->log($request, $id ? 'UPDATE' : 'CREATE', $line->id, $line->line_code);
        Inertia::flash('toast', ['type' => 'success', 'message' => __($id ? 'machines.toast.updated' : 'machines.toast.created', ['code' => $line->line_code])]);

        return back();
    }

    public function destroy(Request $request, MachineLine $line): RedirectResponse
    {
        Gate::authorize('manage-machines');

        $line->deleted_by = $request->user()->id;
        $line->is_active = false;
        $line->save();
        $line->delete();
        $this->log($request, 'DELETE', $line->id, $line->line_code);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('machines.toast.deleted')]);

        return to_route('admin.machines.index');
    }

    public function storePlant(StorePlantRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $id = ! empty($data['id']) ? (int) $data['id'] : null;
        unset($data['id']);
        $data['code'] = strtoupper($data['code']);

        $plant = $id ? Plant::findOrFail($id) : new Plant;
        $plant->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('machines.toast.plant_saved')]);

        return back();
    }

    public function destroyPlant(Request $request, Plant $plant): RedirectResponse
    {
        Gate::authorize('manage-machines');

        if (MachineLine::where('plant_id', $plant->id)->exists()) {
            return back()->withErrors(['plant' => __('machines.errors.plant_in_use')]);
        }

        $plant->deleted_by = $request->user()->id;
        $plant->save();
        $plant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('machines.toast.plant_deleted')]);

        return back();
    }

    /** @return array<string, array<int, string>> */
    private function options(): array
    {
        return [
            'categories' => MachineLine::CATEGORIES,
            'statuses' => MachineLine::STATUSES,
            'automation' => MachineLine::AUTOMATION,
            'capacityUnits' => MachineLine::CAPACITY_UNITS,
            'equipmentTypes' => Equipment::TYPES,
            'conditions' => Equipment::CONDITIONS,
            'manualStatuses' => Equipment::MANUAL_STATUSES,
        ];
    }

    private function log(Request $request, string $action, int $id, string $label): void
    {
        $user = $request->user();
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'MACHINE',
            'record_id' => (string) $id,
            'record_label' => $label,
            'old_data' => null,
            'new_data' => null,
        ]);
    }
}
