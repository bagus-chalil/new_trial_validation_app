<?php

namespace App\Http\Controllers;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use Illuminate\View\View;

/**
 * Public, unauthenticated read-only page a QR code on a printed IPC report links to — lets
 * anyone holding the physical/PDF document confirm who approved a given stage and when, without
 * needing an account. Deliberately outside the `auth` middleware group (see routes/batches.php)
 * and deliberately minimal: only the fields needed to confirm authenticity are shown, not the
 * full inspection data behind the `auth`-gated Approval/Print pages.
 */
class VerificationController extends Controller
{
    public function show(IpcBatch $batch, string $stage): View
    {
        $batch->load(['masterProduct', 'masterLine']);

        $approval = IpcApproval::query()
            ->where('ipc_batch_id', $batch->id)
            ->where('stage', $stage)
            ->with('approver')
            ->first();

        return view('verify.show', [
            'batch' => $batch,
            'stage' => $stage,
            'stageLabel' => IpcApproval::STAGE_LABELS[$stage] ?? $stage,
            'approval' => $approval,
        ]);
    }
}
