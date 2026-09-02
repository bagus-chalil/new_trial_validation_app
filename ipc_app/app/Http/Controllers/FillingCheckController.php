<?php

namespace App\Http\Controllers;

use App\Actions\FillingChecks\SaveFillingCheck;
use App\Http\Requests\SaveFillingCheckRequest;
use App\Models\FillingCheck;
use App\Models\IpcBatch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FillingCheckController extends Controller
{
    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');

        $batch->load(['masterProduct', 'masterLine', 'fillingCheck.samples']);

        return Inertia::render('filling-check/edit', [
            'batch' => $batch,
            'fillingCheck' => $batch->fillingCheck,
            'isReadOnly' => (bool) $batch->fillingCheck?->completed_at,
            'decisions' => FillingCheck::DECISIONS,
        ]);
    }

    public function update(SaveFillingCheckRequest $request, IpcBatch $batch, SaveFillingCheck $action): RedirectResponse
    {
        abort_unless($batch->startupCheck?->completed_at, 403, 'Startup Check untuk batch ini belum selesai.');
        abort_if($batch->fillingCheck?->completed_at, 403, 'Filling Check untuk batch ini sudah selesai dan bersifat read-only.');

        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('batches.show', $batch)->with('success', 'Filling Check tersimpan.');
    }
}
