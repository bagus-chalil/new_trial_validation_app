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
        $batch->load(['masterProduct', 'masterLine', 'startupCheck']);

        return Inertia::render('startup-check/edit', [
            'batch' => $batch,
            'startupCheck' => $batch->startupCheck,
            'isReadOnly' => (bool) $batch->startupCheck?->completed_at,
            'checklistGroups' => StartupCheck::checklistGroups(),
        ]);
    }

    public function update(SaveStartupCheckRequest $request, IpcBatch $batch, SaveStartupCheck $action): RedirectResponse
    {
        abort_if($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('batches.show', $batch)->with('success', 'Startup Check tersimpan.');
    }
}
