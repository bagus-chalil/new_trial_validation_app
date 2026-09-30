<?php

namespace App\Http\Controllers;

use App\Models\IpcApproval;
use App\Models\IpcBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** A batch's active check stage → the Eloquent relation guarding it, and the edit route it links to. */
    private const CHECK_STAGES = [
        IpcBatch::STAGE_STARTUP => ['relation' => 'startupCheck', 'route' => 'startup-check.edit', 'label' => 'Startup Check'],
        IpcBatch::STAGE_FILLING => ['relation' => 'fillingCheck', 'route' => 'filling-check.edit', 'label' => 'Filling Check'],
        IpcBatch::STAGE_PACKING => ['relation' => 'packingCheck', 'route' => 'packing-check.edit', 'label' => 'Packing Check'],
        IpcBatch::STAGE_FINISHED => ['relation' => 'finishedCheck', 'route' => 'finished-check.edit', 'label' => 'Finished Check'],
    ];

    private const NEEDS_ACTION_LIMIT = 8;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $stageCounts = IpcBatch::query()
            ->selectRaw('current_stage, count(*) as total')
            ->groupBy('current_stage')
            ->pluck('total', 'current_stage');

        $stageBreakdown = collect(IpcBatch::STAGES)
            ->map(fn (string $stage) => ['stage' => $stage, 'count' => (int) ($stageCounts[$stage] ?? 0)])
            ->values();

        $activeBatches = (int) $stageCounts->except(IpcBatch::STAGE_COMPLETED)->sum();

        // whereBetween (not whereDate) so the (current_stage, updated_at) index is usable.
        $completedToday = IpcBatch::query()
            ->where('current_stage', IpcBatch::STAGE_COMPLETED)
            ->whereBetween('updated_at', [today(), today()->endOfDay()])
            ->count();

        // Counted and limited in SQL — only the 8 shown rows are ever loaded.
        $checkQuery = $this->checkItemsQuery();
        $checkCount = (clone $checkQuery)->count();
        $needsAction = $this->checkItemsNeedingAction($checkQuery->limit(self::NEEDS_ACTION_LIMIT));

        $approvalCount = 0;
        if ($user->isApprover()) {
            $approvalQuery = IpcBatch::query()->where('current_stage', IpcBatch::STAGE_APPROVAL)->pendingApproval();
            $approvalCount = (clone $approvalQuery)->count();
            $remaining = self::NEEDS_ACTION_LIMIT - $needsAction->count();
            if ($remaining > 0) {
                $needsAction = $needsAction->concat($this->approvalItemsNeedingAction($approvalQuery->limit($remaining)));
            }
        }

        return Inertia::render('dashboard', [
            'stats' => [
                'activeBatches' => $activeBatches,
                'needsActionCount' => $checkCount + $approvalCount,
                'pendingApprovalBatches' => (int) ($stageCounts[IpcBatch::STAGE_APPROVAL] ?? 0),
                'completedToday' => $completedToday,
            ],
            'stageBreakdown' => $stageBreakdown,
            'needsAction' => $needsAction->values(),
        ]);
    }

    /** Batches currently sitting on a check stage whose own check isn't done yet. */
    private function checkItemsQuery(): Builder
    {
        return IpcBatch::query()->where(function (Builder $query) {
            foreach (self::CHECK_STAGES as $stage => $config) {
                $query->orWhere(fn (Builder $q) => $q->where('current_stage', $stage)
                    ->whereDoesntHave($config['relation'], fn ($q) => $q->whereNotNull('completed_at')));
            }
        });
    }

    /** One item per batch currently sitting on a check stage whose own check isn't done yet. */
    private function checkItemsNeedingAction(Builder $query)
    {
        return $query
            ->with('masterProduct:id,product_name,fg_code')
            ->latest('id')
            ->get()
            ->map(fn (IpcBatch $batch) => [
                'id' => $batch->id,
                'no_batch' => $batch->no_batch,
                'product_name' => $batch->masterProduct?->product_name,
                'stage' => $batch->current_stage,
                'reason' => self::CHECK_STAGES[$batch->current_stage]['label'].' belum diisi',
                'href' => route(self::CHECK_STAGES[$batch->current_stage]['route'], $batch),
            ]);
    }

    /** One item per batch waiting on this approver's decision on at least one of its 3 stages. */
    private function approvalItemsNeedingAction(Builder $query)
    {
        return $query
            ->with([
                'masterProduct:id,product_name,fg_code',
                'startupCheck:id,ipc_batch_id,completed_at',
                'fillingCheck:id,ipc_batch_id,completed_at',
                'packingCheck:id,ipc_batch_id,completed_at',
                'finishedCheck:id,ipc_batch_id,completed_at',
                'approvals',
            ])
            ->latest('id')
            ->get()
            ->map(function (IpcBatch $batch) {
                $pending = IpcApproval::pendingStagesFor($batch);

                if ($pending->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $batch->id,
                    'no_batch' => $batch->no_batch,
                    'product_name' => $batch->masterProduct?->product_name,
                    'stage' => IpcBatch::STAGE_APPROVAL,
                    'reason' => 'Menunggu approval: '.$pending->map(fn (string $stage) => IpcApproval::STAGE_LABELS[$stage])->join(', '),
                    'href' => route('approval.edit', $batch),
                ];
            })
            ->filter()
            ->values();
    }
}
