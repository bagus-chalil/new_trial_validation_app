<?php

namespace App\Http\Controllers;

use App\Actions\MicroChecks\SaveMicroCheck;
use App\Http\Requests\SaveMicroCheckRequest;
use App\Models\IpcBatch;
use App\Models\MicroCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MicroCheckController extends Controller
{
    /**
     * The Microbiologist's inbox: batches whose Finished Good has been saved at least once but
     * isn't finalized yet — split into "belum diisi" and "sudah diisi" (still correctable).
     */
    public function index(Request $request): Response
    {
        $batches = IpcBatch::query()
            ->whereHas('finishedCheck', fn ($query) => $query->whereNull('completed_at'))
            ->with(['masterProduct:id,fg_code,product_name', 'masterLine:id,name', 'microCheck'])
            ->latest('id')
            ->paginate(20)
            ->through(fn (IpcBatch $batch) => [
                'id' => $batch->id,
                'no_batch' => $batch->no_batch,
                'product_name' => $batch->masterProduct?->product_name,
                'fg_code' => $batch->masterProduct?->fg_code,
                'line' => $batch->masterLine?->name,
                'filled' => (bool) $batch->microCheck?->isComplete(),
                'filled_at' => $batch->microCheck?->updated_at,
            ]);

        return Inertia::render('micro-checks/index', ['batches' => $batches]);
    }

    public function edit(IpcBatch $batch): Response
    {
        abort_unless($batch->hasFinishedStage(), 404);
        Gate::authorize('viewFinished', $batch);

        $batch->load([
            'masterProduct',
            'masterLine',
            'finishedCheck',
            'microCheck.user',
            'microCheck.revisions' => fn ($query) => $query->latest('revision_no'),
            'microCheck.revisions.user',
        ]);

        return Inertia::render('micro-checks/edit', [
            'batch' => $batch,
            'microCheck' => $batch->microCheck,
            'parameters' => MicroCheck::PARAMETERS,
            'defaultStd' => MicroCheck::DEFAULT_STD,
            'isReadOnly' => ! Gate::allows('recordMicroCheck', $batch),
            'finishedCompleted' => (bool) $batch->finishedCheck?->completed_at,
        ]);
    }

    public function update(SaveMicroCheckRequest $request, IpcBatch $batch, SaveMicroCheck $action): RedirectResponse
    {
        $action->handle($batch, $request->user(), $request->validated());

        return redirect()->route('micro-checks.edit', $batch)->with('success', 'Micro Check tersimpan.');
    }
}
