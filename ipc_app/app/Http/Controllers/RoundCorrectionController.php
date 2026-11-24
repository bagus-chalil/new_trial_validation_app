<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\CorrectCheckRound;
use App\Http\Requests\SaveRoundCorrectionRequest;
use App\Models\FillingCheck;
use App\Models\FinishedCheck;
use App\Models\FinishedCheckSample;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcRoundCorrection;
use App\Models\PackingCheck;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where QC fixes the TH Progress round an approver flagged in "Minta Revisi". See
 * CorrectCheckRound for how the correction and its audit trail are stored.
 */
class RoundCorrectionController extends Controller
{
    public function edit(IpcBatch $batch, IpcRoundCorrection $correction): Response
    {
        $this->guard($batch, $correction);

        $stage = $correction->stage;
        $snapshot = CorrectCheckRound::snapshot($batch, $stage, (int) $correction->revision_no);
        abort_if($snapshot === null, 404);

        $batch->load(['masterProduct', 'masterLine']);
        $correction->load('requester:id,name');

        return Inertia::render('corrections/edit', [
            'batch' => $batch,
            'correction' => $correction,
            'stage' => $stage,
            'stageLabel' => IpcApproval::STAGE_LABELS[$stage] ?? $stage,
            'values' => $snapshot['revision'],
            'samples' => $snapshot['samples'],
            'checklistGroups' => $stage === IpcBatch::STAGE_PACKING ? PackingCheck::checklistGroups() : [],
            'sampleGroups' => $stage === IpcBatch::STAGE_FINISHED ? FinishedCheckSample::sampleGroups() : [],
            'decisions' => $stage === IpcBatch::STAGE_FILLING ? FillingCheck::DECISIONS : PackingCheck::DECISIONS,
            'dispositions' => FinishedCheck::DISPOSITIONS,
        ]);
    }

    public function update(SaveRoundCorrectionRequest $request, IpcBatch $batch, IpcRoundCorrection $correction, CorrectCheckRound $action): RedirectResponse
    {
        $this->guard($batch, $correction);

        $action->handle($batch, $request->user(), $correction, $request->validated(), $request->validated('correction_reason'));

        $route = match ($correction->stage) {
            IpcBatch::STAGE_FILLING => 'filling-check.edit',
            IpcBatch::STAGE_PACKING => 'packing-check.edit',
            default => 'finished-check.edit',
        };

        return redirect()->route($route, $batch)->with('success', "Koreksi TH Progress #{$correction->revision_no} tersimpan dan tercatat di audit.");
    }

    private function guard(IpcBatch $batch, IpcRoundCorrection $correction): void
    {
        abort_unless($correction->ipc_batch_id === $batch->id, 404);
        abort_unless($correction->revision_no !== null, 404);
        abort_unless($correction->isOpen(), 403, 'Koreksi ini sudah dikerjakan.');
        // Corrections only exist while the approver's revision request is open.
        abort_unless($batch->hasOpenRevision(), 403, 'Tidak ada permintaan revisi yang terbuka untuk batch ini.');
    }
}
