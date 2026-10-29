<?php

namespace App\Http\Controllers;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Support\BatchFilter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim($request->string('q')->toString());
        $filter = BatchFilter::fromRequest($request);

        $queue = $filter->apply(IpcBatch::query())
            ->when($q !== '', fn ($query) => $query->matchingSearch($q))
            // Finished Check done + at least one stage ready-but-not-Approved, evaluated in SQL
            // (see IpcBatch::scopePendingApproval) so this stays a paginated, indexed query as
            // batches grow, instead of loading every finished batch into PHP. Mirrors
            // ApprovalController::guardFinished() — a batch belongs here only once its approval
            // screen is actually reachable.
            ->pendingApproval()
            ->with([
                'masterProduct:id,product_name,fg_code',
                'masterLine:id,name',
                'startupCheck:id,ipc_batch_id,completed_at',
                'fillingCheck:id,ipc_batch_id,completed_at',
                'packingCheck:id,ipc_batch_id,completed_at',
                'finishedCheck:id,ipc_batch_id,completed_at',
                'approvals',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (IpcBatch $batch) => [
                'batch' => $batch,
                'pendingStages' => IpcApproval::pendingStagesFor($batch)
                    ->map(fn (string $stage) => IpcApproval::STAGE_LABELS[$stage])
                    ->values(),
            ]);

        return Inertia::render('approvals/index', [
            'queue' => $queue,
            'filters' => ['q' => $q, ...$filter->toArray()],
            'filterOptions' => BatchFilter::options(),
        ]);
    }
}
