<?php

namespace App\Http\Controllers;

use App\Actions\StartupChecks\SaveStartupCheck;
use App\Http\Requests\SaveStartupCheckRequest;
use App\Models\IpcBatch;
use App\Models\StartupCheck;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StartupCheckController extends Controller
{
    public function edit(IpcBatch $batch): Response
    {
        $batch->load(['masterProduct', 'masterLine', 'startupCheck.bottleWeights']);

        return Inertia::render('startup-check/edit', [
            'batch' => $batch,
            'startupCheck' => $batch->startupCheck,
            'isReadOnly' => (bool) $batch->startupCheck?->completed_at,
            'availabilityFields' => StartupCheck::AVAILABILITY_FIELDS,
            'conformFields' => StartupCheck::CONFORM_FIELDS,
            'statusOptions' => [
                'availability' => [StartupCheck::STATUS_AVAILABLE, StartupCheck::STATUS_NOT_AVAILABLE],
                'conform' => [StartupCheck::STATUS_CONFORM, StartupCheck::STATUS_NOT_CONFORM],
            ],
        ]);
    }

    public function update(SaveStartupCheckRequest $request, IpcBatch $batch, SaveStartupCheck $action): RedirectResponse
    {
        abort_if($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('batches.index')->with('success', 'Startup Check tersimpan.');
    }
}
