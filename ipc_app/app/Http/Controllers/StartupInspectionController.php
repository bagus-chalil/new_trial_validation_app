<?php

namespace App\Http\Controllers;

use App\Actions\StartupInspections\SaveStartupInspection;
use App\Http\Requests\SaveStartupInspectionRequest;
use App\Models\IpcBatch;
use App\Models\MasterTestType;
use App\Models\StartupInspectionItem;
use Illuminate\Http\RedirectResponse;
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
            'isReadOnly' => (bool) $inspection?->completed_at,
            'parameterKeys' => StartupInspectionItem::PARAMETER_KEYS,
            'statusOptions' => [
                StartupInspectionItem::STATUS_OK,
                StartupInspectionItem::STATUS_PARTIAL_OK,
                StartupInspectionItem::STATUS_NOT_OK,
            ],
            'testTypes' => $testTypes,
        ]);
    }

    public function update(SaveStartupInspectionRequest $request, IpcBatch $batch, SaveStartupInspection $action): RedirectResponse
    {
        abort_if($batch->startupInspection?->completed_at, 403, 'Start Inspection untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('startup-check.edit', $batch)->with('success', 'Start Inspection tersimpan.');
    }
}
