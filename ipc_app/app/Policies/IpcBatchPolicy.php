<?php

namespace App\Policies;

use App\Models\IpcBatch;
use App\Models\User;

class IpcBatchPolicy
{
    /**
     * Any Staff (or Admin) can continue any batch's stage data — batches are shift-worked, not
     * owned by whoever started them, so a batch a Staff started in one shift must be editable by
     * a different Staff finishing it in the next shift. Approver is deliberately
     * excluded: Approver only approves (see the separate `approve-ipc` gate).
     */
    public function update(User $user, IpcBatch $batch): bool
    {
        return $user->isStaff();
    }

    /** Only Staff (or Admin) open new batches — Approver never does. */
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * Opening a batch's overview page. Staff/Admin: any. Approver: only from Approval onwards
     * (approve, then look at it once printed/completed). Mirrors IpcBatch::scopeVisibleTo().
     */
    public function view(User $user, IpcBatch $batch): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->isApprover() && in_array($batch->current_stage, IpcBatch::APPROVER_VISIBLE_STAGES, true);
    }

    /** The Finished Good page: Staff only. */
    public function viewFinished(User $user, IpcBatch $batch): bool
    {
        return $user->isStaff();
    }

    /** Printed reports: Staff/Admin always, Approver once the batch reached Approval. */
    public function print(User $user, IpcBatch $batch): bool
    {
        return $user->isStaff()
            || ($user->isApprover() && in_array($batch->current_stage, IpcBatch::APPROVER_VISIBLE_STAGES, true));
    }
}
