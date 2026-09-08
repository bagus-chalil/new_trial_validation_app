<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable per-decision snapshot for one IpcApproval row — added 2026-09-08 because
 * SaveApproval::handle() upserts ipc_approvals in place (updateOrCreate keyed on
 * ipc_batch_id+stage), so re-deciding a stage (e.g. Approved -> Rejected) silently overwrote the
 * prior decision/approver/remarks with no trace, unlike Filling/Packing/Finished Check which each
 * already had this kind of TH_PROGRESS revision history. Written on every SaveApproval call, same
 * append-only precedent as FillingCheckRevision/PackingCheckRevision/FinishedCheckRevision.
 */
class IpcApprovalRevision extends Model
{
    protected $fillable = [
        'ipc_approval_id',
        'revision_no',
        'decision',
        'approver_user_id',
        'remarks',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function approval()
    {
        return $this->belongsTo(IpcApproval::class, 'ipc_approval_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
