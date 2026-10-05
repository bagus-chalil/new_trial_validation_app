<?php

namespace App\Http\Controllers;

use App\Actions\StartupInspections\SaveStartupInspection;
use App\Actions\StartupInspections\SaveStartupInspectionMasterBox;
use App\Http\Requests\SaveStartupInspectionMasterBoxRequest;
use App\Http\Requests\SaveStartupInspectionRequest;
use App\Models\IpcBatch;
use App\Models\MasterTestType;
use App\Models\StartupInspectionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StartupInspectionController extends Controller
{
    public function edit(IpcBatch $batch): Response
    {
        $batch->load(['masterProduct', 'masterLine']);

        $inspection = $batch->startupInspection()->with(['items', 'samples', 'testResults'])->first();

        $testTypes = MasterTestType::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        return Inertia::render('startup-inspection/edit', [
            'batch' => $batch,
            'startupInspection' => $inspection,
            'isReadOnly' => ! Gate::allows('update', $batch) || (bool) $inspection?->completed_at,
            'parameterKeys' => StartupInspectionItem::PARAMETER_KEYS,
            'statusOptions' => StartupInspectionItem::STATUSES,
            'notApplicableKeys' => StartupInspectionItem::NOT_APPLICABLE_KEYS,
            'notApplicableStatus' => StartupInspectionItem::STATUS_NA,
            'testTypes' => $testTypes,
            'masterBoxOnly' => Gate::allows('update', $batch) && self::masterBoxLockReason($batch) === null,
        ]);
    }

    public function update(SaveStartupInspectionRequest $request, IpcBatch $batch, SaveStartupInspection $action): RedirectResponse
    {
        abort_if($batch->startupInspection?->completed_at, 403, 'Start Inspection untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('startup-check.edit', $batch)->with('success', 'Start Inspection tersimpan.');
    }

    public function updateMasterBox(SaveStartupInspectionMasterBoxRequest $request, IpcBatch $batch, SaveStartupInspectionMasterBox $action): RedirectResponse
    {
        $reason = self::masterBoxLockReason($batch);
        abort_if($reason !== null, 403, $reason ?? '');

        $action->handle($batch->startupInspection, $request->validated()['samples']);

        return redirect()->route('packing-check.edit', $batch)->with('success', 'Weight Master Box tersimpan.');
    }

    /**
     * Weight Master Box is optional at Start Inspection (IPC often can't weigh master boxes
     * that early), so it gets one more chance once the batch reaches Packing: after Start
     * Inspection is completed, Filling has a save (which is what opens Packing), and Packing
     * isn't finalized yet. It's a one-time fill — once any sample has a value, whether typed
     * at Start Inspection or here, it's locked for good.
     *
     * Returns null when the late fill is allowed, otherwise the reason it isn't.
     */
    public static function masterBoxLockReason(IpcBatch $batch): ?string
    {
        $inspection = $batch->startupInspection;

        return match (true) {
            ! $inspection?->completed_at => 'Start Inspection untuk batch ini belum selesai.',
            ! $batch->fillingCheck => 'Weight Master Box baru bisa diisi setelah Filling Check disimpan.',
            (bool) $batch->packingCheck?->completed_at => 'Packing Check untuk batch ini sudah selesai.',
            $inspection->samples()->whereNotNull('weight_master_box')->exists() => 'Weight Master Box untuk batch ini sudah diisi.',
            default => null,
        };
    }
}
