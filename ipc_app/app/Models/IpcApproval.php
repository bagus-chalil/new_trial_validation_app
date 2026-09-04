<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
