<?php

namespace App\Http\Controllers;

use App\Actions\FinishedChecks\SaveFinishedCheck;
use App\Http\Requests\SaveFinishedCheckRequest;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcBatch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinishedCheckController extends Controller
{
    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini belum selesai.');

        $batch->load(['masterProduct', 'masterLine', 'finishedCheck.samples']);

        $samples = $batch->finishedCheck
            ? $batch->finishedCheck->samples->keyBy('parameter_key')
            : collect();

        return Inertia::render('finished-check/edit', [
            'batch' => $batch,
            'finishedCheck' => $batch->finishedCheck,
            'samples' => $samples,
            'isReadOnly' => (bool) $batch->finishedCheck?->completed_at,
            'sampleGroups' => FinishedCheckSample::sampleGroups(),
            'dispositions' => FinishedCheck::DISPOSITIONS,
        ]);
    }

    public function update(SaveFinishedCheckRequest $request, IpcBatch $batch, SaveFinishedCheck $action): RedirectResponse
    {
        abort_unless($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini belum selesai.');
        abort_if($batch->finishedCheck?->completed_at, 403, 'Finished Check untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('batches.show', $batch)->with('success', 'Finished Check tersimpan.');
    }
}
