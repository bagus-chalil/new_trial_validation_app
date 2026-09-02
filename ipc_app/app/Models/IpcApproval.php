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

    // Finished-stage approval uses Accepted/Accepted With Remarks/Rejected (see
    // FinishedCheck::DISPOSITIONS); startup and filling_packing approvals are expected to
    // reuse the check-level Passed/Hold/Reject vocabulary. Not yet verified against the PDF.
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
}
