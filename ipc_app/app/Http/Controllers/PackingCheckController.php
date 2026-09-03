<?php

namespace App\Http\Controllers;

use App\Actions\PackingChecks\SavePackingCheck;
use App\Http\Requests\SavePackingCheckRequest;
use App\Models\IpcBatch;
use App\Models\PackingCheck;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackingCheckController extends Controller
{
    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini belum selesai.');

        $batch->load(['masterProduct', 'masterLine', 'packingCheck']);

        return Inertia::render('packing-check/edit', [
            'batch' => $batch,
            'packingCheck' => $batch->packingCheck,
            'isReadOnly' => (bool) $batch->packingCheck?->completed_at,
            'checklistGroups' => PackingCheck::checklistGroups(),
            'decisions' => PackingCheck::DECISIONS,
        ]);
    }

    public function update(SavePackingCheckRequest $request, IpcBatch $batch, SavePackingCheck $action): RedirectResponse
    {
        abort_unless($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini belum selesai.');
        abort_if($batch->packingCheck?->completed_at, 403, 'Packing Check untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('batches.show', $batch)->with('success', 'Packing Check tersimpan.');
    }
}
