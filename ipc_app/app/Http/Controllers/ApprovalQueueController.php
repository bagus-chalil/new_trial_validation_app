<?php

namespace App\Http\Controllers;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalQueueController extends Controller
{
    public function index(): Response
    {
        $queue = IpcBatch::query()
            // Mirrors ApprovalController::guardFinished() — every route on that screen (incl.
            // every per-stage detail page) 403s until Finished Check is done, regardless of any
            // individual stage's own readiness, so a batch belongs in this queue only once it's
            // actually clickable. A plain `current_stage != startup` filter is looser than that
            // and let a real batch (still at "filling", only Startup done) appear here with a
            // dead 403 link — caught via live verification against real data, not in the
            // original design.
            ->whereHas('finishedCheck', fn ($query) => $query->whereNotNull('completed_at'))
            ->with([
                'masterProduct:id,product_name,fg_code',
                'masterLine:id,name',
                'startupCheck:id,ipc_batch_id,completed_at',
                'fillingCheck:id,ipc_batch_id,completed_at',
                'packingCheck:id,ipc_batch_id,completed_at',
                'finishedCheck:id,ipc_batch_id,completed_at',
                'approvals',
            ])
            ->latest('id')->get()
            ->map(function (IpcBatch $batch) {
                $pending = IpcApproval::pendingStagesFor($batch);

                if ($pending->isEmpty()) {
                    return null;
                }

                return [
                    'batch' => $batch,
                    'pendingStages' => $pending->map(fn (string $stage) => IpcApproval::STAGE_LABELS[$stage])->values(),
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('approvals/index', ['queue' => $queue]);
    }
}
