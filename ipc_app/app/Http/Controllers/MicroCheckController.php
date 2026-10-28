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
     * The Microbiologist's inbox: batches whose Finished Good is finalized and whose Micro Check
     * isn't finalized yet — "belum diisi" or progress saved (still editable).
     */
    public function index(Request $request): Response
    {
        $batches = IpcBatch::query()
            ->whereHas('finishedCheck', fn ($query) => $query->whereNotNull('completed_at'))
            ->whereDoesntHave('microCheck', fn ($query) => $query->whereNotNull('completed_at'))
            ->with(['masterProduct:id,fg_code,product_name', 'masterLine:id,name', 'microCheck'])
            ->latest('id')
            ->paginate(20)
            ->through(fn (IpcBatch $batch) => [
                'id' => $batch->id,
                'no_batch' => $batch->no_batch,
                'product_name' => $batch->masterProduct?->product_name,
                'fg_code' => $batch->masterProduct?->fg_code,
                'line' => $batch->masterLine?->name,
                'filled' => $batch->microCheck !== null,
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
            'microCompleted' => (bool) $batch->microCheck?->completed_at,
        ]);
    }

    public function update(SaveMicroCheckRequest $request, IpcBatch $batch, SaveMicroCheck $action): RedirectResponse
    {
        $finalize = $request->boolean('finalize');
        $action->handle($batch, $request->user(), $request->validated(), $finalize);

        if ($finalize) {
            return redirect()->route('micro-checks.index')->with('success', 'Micro Check selesai — batch dikirim ke Approval.');
        }

        return redirect()->route('micro-checks.edit', $batch)->with('success', 'Progress Micro Check tersimpan.');
    }
}
