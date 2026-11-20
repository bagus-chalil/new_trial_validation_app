<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class IpcApproval extends Model
{
    public const STAGE_STARTUP = 'startup';

    public const STAGE_FILLING = 'filling';

    public const STAGE_PACKING = 'packing';

    public const STAGE_FINISHED = 'finished';

    /**
     * Pre-2026-10-20 combined "Filling & Packing" stage. Split into STAGE_FILLING + STAGE_PACKING
     * (migration 2026_10_20_000000); kept only so old printed QR codes (verify/{batch}/filling_packing)
     * still resolve — see VerificationController. Never stored in ipc_approvals any more.
     */
    public const LEGACY_STAGE_FILLING_PACKING = 'filling_packing';

    public const STAGES = [
        self::STAGE_STARTUP,
        self::STAGE_FILLING,
        self::STAGE_PACKING,
        self::STAGE_FINISHED,
    ];

    /**
     * Every stage that can gate a batch's advance to Print, across all flows (see
     * requiredStagesFor() for the per-flow subset). Startup is excluded since 2026-09-23, when its
     * card was hidden from the Approval overview per direct user request — with no way to reach it
     * from the overview, requiring it left batches stuck at Approval forever even after every
     * visible stage was Approved. Its detail page still exists and an optional Startup decision is
     * still recorded, it just no longer gates the batch.
     */
    public const APPROVAL_REQUIRED_STAGES = [
        self::STAGE_FILLING,
        self::STAGE_PACKING,
        self::STAGE_FINISHED,
    ];

    public const STAGE_LABELS = [
        self::STAGE_STARTUP => 'Startup',
        self::STAGE_FILLING => 'Filling',
        self::STAGE_PACKING => 'Packing',
        self::STAGE_FINISHED => 'Finished',
        self::LEGACY_STAGE_FILLING_PACKING => 'Filling & Packing',
    ];

    public const DECISION_APPROVED = 'Approved';

    public const DECISION_REJECTED = 'Rejected';

    // Confirmed 2026-09-04 against the real Power Apps export (Controls/2171.json,
    // Controls/2370.json, Controls/2608.json): legacy's approval action has NO decision field at
    // all, just a bare Approval="Y" Patch — no reject option, no remarks, no approver identity.
    // Approved/Rejected is this app's own addition on top of that (confirmed with the user
    // 2026-09-04), not a ported vocabulary.
    /**
     * Added 2026-11-18 (user request): the approver sends the report back to QC to be corrected
     * instead of rejecting it. The chosen stage(s) are reopened for editing; once QC finalizes
     * them again the decision is cleared and the report is back in the approval queue. Rejected
     * stays a final, separate decision.
     */
    public const DECISION_REVISION = 'Revision';

    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_REVISION,
        self::DECISION_REJECTED,
    ];

    /**
     * Two-level approval (2026-11-21). Level 1 = QC Verifikator, stored in the original columns
     * (decision / approver_user_id / remarks / approved_at). Level 2 = QC Team Leader / Part Leader /
     * Supervisor, stored in the l2_* columns of the same row. A stage is approved only when both
     * levels are Approved.
     */
    public const LEVEL_1 = 1;

    public const LEVEL_2 = 2;

    /** Decisions a Level 2 approver can make (sending a report back to QC for revision stays Level 1's). */
    public const L2_DECISIONS = [
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
        'l2_decision',
        'l2_approver_user_id',
        'l2_remarks',
        'l2_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'l2_decided_at' => 'datetime',
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

    public function l2Approver()
    {
        return $this->belongsTo(User::class, 'l2_approver_user_id');
    }

    /** Both levels Approved — the only state that counts as "this stage is approved". */
    public function isFullyApproved(): bool
    {
        return $this->decision === self::DECISION_APPROVED && $this->l2_decision === self::DECISION_APPROVED;
    }

    /** Level 1 Approved and Level 2 has not (or not yet positively) decided: Level 2's turn. */
    public function awaitsLevel2(): bool
    {
        return $this->decision === self::DECISION_APPROVED && $this->l2_decision !== self::DECISION_APPROVED;
    }

    /**
     * SQL twin of isFullyApproved(): constrain an ipc_approvals query (or whereHas closure) to rows
     * whose both levels are Approved.
     */
    public function scopeFullyApproved($query)
    {
        return $query->where('decision', self::DECISION_APPROVED)->where('l2_decision', self::DECISION_APPROVED);
    }

    public function revisions()
    {
        return $this->hasMany(IpcApprovalRevision::class);
    }

    /**
     * The approval that signs a stage's report. Startup has had no approval card since
     * 2026-09-23, so its report falls back to the QC Coordinator's Filling (else Packing) approval
     * (user request 2026-10-07: approving the batch also verifies its Startup form). A real Startup
     * approval, if one was ever recorded, still wins.
     *
     * @param  iterable<self>  $approvals
     */
    public static function forReport(iterable $approvals, string $stage): ?self
    {
        $byStage = collect($approvals)->keyBy('stage');

        if ($stage === self::STAGE_STARTUP) {
            return $byStage->get(self::STAGE_STARTUP) ?? $byStage->get(self::STAGE_FILLING) ?? $byStage->get(self::STAGE_PACKING);
        }

        if ($stage === self::LEGACY_STAGE_FILLING_PACKING) {
            return $byStage->get(self::STAGE_FILLING) ?? $byStage->get(self::STAGE_PACKING);
        }

        return $byStage->get($stage);
    }

    /**
     * Whether the underlying check for a given approval stage is done, i.e. this stage can
     * actually be approved yet. Filling, Packing and Finished each have their own report and
     * approval (Filling's output is WIP, Packing's is FG — user decision 2026-10-20).
     */
    public static function stageReady(IpcBatch $batch, string $stage): bool
    {
        return match ($stage) {
            self::STAGE_STARTUP => (bool) $batch->startupCheck?->completed_at,
            self::STAGE_FILLING => $batch->hasFillingStage() && (bool) $batch->fillingCheck?->completed_at,
            self::STAGE_PACKING => $batch->hasPackingStage() && (bool) $batch->packingCheck?->completed_at,
            self::STAGE_FINISHED => $batch->hasFinishedStage() && (bool) $batch->finishedCheck?->completed_at,
            // Flow Lengkap's combined Fillpack + FG form: one decision covering all three stages.
            IpcReportDocument::KEY_FILLING_PACKING_FG => self::combinedStagesFor($batch) !== []
                && collect(self::combinedStagesFor($batch))->every(fn (string $part) => self::stageReady($batch, $part)),
            default => false,
        };
    }

    /**
     * The stages one combined-form decision (Fillpack + FG on Lengkap, Packing + FG on Packing + FG)
     * is written to; empty for flows that have no combined form.
     *
     * @return list<string>
     */
    public static function combinedStagesFor(IpcBatch $batch): array
    {
        return match ($batch->flow_type) {
            IpcBatch::FLOW_FULL => [self::STAGE_FILLING, self::STAGE_PACKING, self::STAGE_FINISHED],
            IpcBatch::FLOW_PACKING_FG => [self::STAGE_PACKING, self::STAGE_FINISHED],
            default => [],
        };
    }

    /**
     * Which approval stages apply to this batch's flow at all: Filling flow → Filling only (output
     * is WIP, no Finished Good); Packing + FG → Packing and Finished; Full → all three. Derived
     * from IpcBatch::activeStages().
     *
     * @return list<string>
     */
    public static function requiredStagesFor(IpcBatch $batch): array
    {
        $stages = array_values(array_filter(
            self::APPROVAL_REQUIRED_STAGES,
            fn (string $stage) => in_array($stage, $batch->activeStages(), true),
        ));

        // Packing + FG and Lengkap each print Startup as its own form - Startup (FR.QAC.182.03), Packing and Finished
        // (FR.QAC.193.00), user decision 2026-10-28 - so Startup gets its own approval card there
        // and gates Print like the other two. Every other flow is unchanged.
        if (in_array($batch->flow_type, [IpcBatch::FLOW_PACKING_FG, IpcBatch::FLOW_FULL], true)) {
            array_unshift($stages, self::STAGE_STARTUP);
        }

        return $stages;
    }

    /**
     * The level that must decide next on a stage: 1 until Level 1 is Approved, then 2.
     *
     * @param  iterable<self>  $approvals
     */
    public static function nextLevelFor(iterable $approvals, string $stage): int
    {
        $approval = collect($approvals)->firstWhere('stage', $stage);

        // A Level 2 refusal sends the stage back to Level 1 to decide again.
        return $approval && $approval->decision === self::DECISION_APPROVED && $approval->l2_decision !== self::DECISION_REJECTED
            ? self::LEVEL_2
            : self::LEVEL_1;
    }

    /**
     * Queue/dashboard wording for a pending stage: "Filling (L1)" until Level 1 approves, then
     * "Filling (L2)". Requires `approvals` to be eager-loaded on $batch.
     */
    public static function pendingLabel(IpcBatch $batch, string $stage): string
    {
        return self::STAGE_LABELS[$stage].' (L'.self::nextLevelFor($batch->approvals, $stage).')';
    }

    /**
     * Which required approval stages are ready to decide but not yet Approved (by both levels) for this batch.
     * Requires `approvals` to already be eager-loaded on $batch to avoid N+1 queries.
     */
    public static function pendingStagesFor(IpcBatch $batch): Collection
    {
        $approvals = $batch->approvals->keyBy('stage');

        return collect(self::requiredStagesFor($batch))->filter(
            fn (string $stage) => self::stageReady($batch, $stage)
                && ! optional($approvals->get($stage))->isFullyApproved()
        )->values();
    }
}
