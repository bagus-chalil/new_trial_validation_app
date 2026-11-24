<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An approver's request to correct one saved TH Progress round (or reopen Startup), and — once
 * QC has done it — the audit record of what changed. See the create migration for the why.
 */
class IpcRoundCorrection extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'ipc_batch_id',
        'stage',
        'revision_no',
        'status',
        'requested_by',
        'request_note',
        'requested_at',
        'corrected_by',
        'correction_reason',
        'corrected_at',
        'before_data',
        'after_data',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'corrected_at' => 'datetime',
            'before_data' => 'array',
            'after_data' => 'array',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(IpcBatch::class, 'ipc_batch_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function corrector()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
