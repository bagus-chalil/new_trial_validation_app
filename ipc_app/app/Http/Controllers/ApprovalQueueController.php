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

        $user = $request->user();
        // An L1-only approver sees only Level 1 work and an L2-only approver only Level 2; Admin
        // (both levels) may pick one with the filter, or see everything.
        $lockedLevel = $user->isAdmin() ? null : ($user->isApproverL1() && ! $user->isApproverL2() ? IpcApproval::LEVEL_1
            : ($user->isApproverL2() && ! $user->isApproverL1() ? IpcApproval::LEVEL_2 : null));
        $requested = (int) $request->input('level');
        $level = $lockedLevel ?? (in_array($requested, [IpcApproval::LEVEL_1, IpcApproval::LEVEL_2], true) ? $requested : null);

        $levelIds = $level === null ? null : $this->batchIdsAtLevel($level);

        $queue = $filter->apply(IpcBatch::query())
            ->when($q !== '', fn ($query) => $query->matchingSearch($q))
            ->when($levelIds !== null, fn ($query) => $query->whereIn('ipc_batches.id', $levelIds))
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
                    ->when($level !== null, fn ($stages) => $stages->filter(
                        fn (string $stage) => IpcApproval::nextLevelFor($batch->approvals, $stage) === $level))
                    ->map(fn (string $stage) => IpcApproval::pendingLabel($batch, $stage))
                    ->values(),
            ]);

        return Inertia::render('approvals/index', [
            'queue' => $queue,
            'filters' => ['q' => $q, ...$filter->toArray()],
            'level' => $level,
            'levelLocked' => $lockedLevel !== null,
            'filterOptions' => BatchFilter::options(),
        ]);
    }

    /**
     * Ids of pending batches that have at least one pending stage waiting on the given level.
     * The pending set is small (it is the approvers' work queue), so the per-stage level rule
     * (IpcApproval::nextLevelFor) is evaluated in PHP instead of duplicating it in SQL.
     *
     * @return list<int>
     */
    private function batchIdsAtLevel(int $level): array
    {
        return IpcBatch::query()
            ->pendingApproval()
            ->with('approvals', 'startupCheck', 'fillingCheck', 'packingCheck', 'finishedCheck')
            ->get()
            ->filter(fn (IpcBatch $batch) => IpcApproval::pendingStagesFor($batch)
                ->contains(fn (string $stage) => IpcApproval::nextLevelFor($batch->approvals, $stage) === $level))
            ->pluck('id')
            ->all();
    }
}
