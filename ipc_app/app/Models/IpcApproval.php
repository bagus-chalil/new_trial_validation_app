<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class IpcApproval extends Model
{
    public const STAGE_STARTUP = 'startup';

    public const STAGE_FILLING_PACKING = 'filling_packing';

    public const STAGE_FINISHED = 'finished';

    public const STAGES = [
        self::STAGE_STARTUP,
        self::STAGE_FILLING_PACKING,
        self::STAGE_FINISHED,
    ];

    /**
     * Stages that must be Approved before a batch advances to Print. Startup is excluded since
     * 2026-09-23, when its card was hidden from the Approval overview per direct user request —
     * with no way to reach it from the overview, requiring it left batches stuck at Approval
     * forever even after every visible stage was Approved. Its detail page still exists and an
     * optional Startup decision is still recorded, it just no longer gates the batch.
     */
    public const APPROVAL_REQUIRED_STAGES = [
        self::STAGE_FILLING_PACKING,
        self::STAGE_FINISHED,
    ];

    public const STAGE_LABELS = [
        self::STAGE_STARTUP => 'Startup',
        self::STAGE_FILLING_PACKING => 'Filling & Packing',
        self::STAGE_FINISHED => 'Finished',
    ];

    public const DECISION_APPROVED = 'Approved';

    public const DECISION_REJECTED = 'Rejected';

    // Confirmed 2026-09-04 against the real Power Apps export (Controls/2171.json,
    // Controls/2370.json, Controls/2608.json): legacy's approval action has NO decision field at
    // all, just a bare Approval="Y" Patch — no reject option, no remarks, no approver identity.
    // Approved/Rejected is this app's own addition on top of that (confirmed with the user
    // 2026-09-04), not a ported vocabulary.
    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_REJECTED,
    ];

    protected $fillable = [
        'ipc_batch_id',
        'stage',
        'decision',
        'approver_user_id',
        'remarks',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function revisions()
    {
        return $this->hasMany(IpcApprovalRevision::class);
    }

    /**
     * The approval that signs a stage's report. Startup has had no approval card since
     * 2026-09-23, so its report falls back to the QC Coordinator's Filling & Packing approval
     * (user request 2026-10-07: approving the batch also verifies its Startup form). A real Startup
     * approval, if one was ever recorded, still wins.
     *
     * @param  iterable<self>  $approvals
     */
    public static function forReport(iterable $approvals, string $stage): ?self
    {
        $byStage = collect($approvals)->keyBy('stage');

        if ($stage === self::STAGE_STARTUP) {
            return $byStage->get(self::STAGE_STARTUP) ?? $byStage->get(self::STAGE_FILLING_PACKING);
        }

        return $byStage->get($stage);
    }

    /**
     * Whether the underlying check(s) for a given approval stage are done, i.e. this stage can
     * actually be approved yet. "filling_packing" combines two separate check tables into one
     * approval action, matching legacy's FIllingPackingReport_Approval screen exactly.
     */
    public static function stageReady(IpcBatch $batch, string $stage): bool
    {
        return match ($stage) {
            self::STAGE_STARTUP => (bool) $batch->startupCheck?->completed_at,
            self::STAGE_FILLING_PACKING => (bool) ($batch->fillingCheck?->completed_at && $batch->packingCheck?->completed_at),
            self::STAGE_FINISHED => (bool) $batch->finishedCheck?->completed_at,
            default => false,
        };
    }

    /**
     * Which required approval stages are ready to decide but not yet Approved for this batch.
     * Requires `approvals` to already be eager-loaded on $batch to avoid N+1 queries.
     */
    public static function pendingStagesFor(IpcBatch $batch): Collection
    {
        $approvals = $batch->approvals->keyBy('stage');

        return collect(self::APPROVAL_REQUIRED_STAGES)->filter(
            fn (string $stage) => self::stageReady($batch, $stage)
                && optional($approvals->get($stage))->decision !== self::DECISION_APPROVED
        )->values();
    }
}
