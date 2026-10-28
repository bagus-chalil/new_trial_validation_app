<?php

namespace App\Policies;

use App\Models\IpcBatch;
use App\Models\User;

class IpcBatchPolicy
{
    /**
     * Any Staff (or Admin) can continue any batch's stage data — batches are shift-worked, not
     * owned by whoever started them, so a batch a Staff started in one shift must be editable by
     * a different Staff finishing it in the next shift. Approver and Microbiologist are
     * deliberately excluded: Approver only approves (see the separate `approve-ipc` gate), and
     * Microbiologist only fills the Micro Check block (see recordMicroCheck()).
     */
    public function update(User $user, IpcBatch $batch): bool
    {
        return $user->isStaff();
    }

    /** Only Staff (or Admin) open new batches — Approver and Microbiologist never do. */
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /**
     * Opening a batch's overview page. Staff/Admin: any. Approver: only from Approval onwards
     * (approve, then look at it once printed/completed). Microbiologist: only once Finished Good
     * has been saved. Mirrors IpcBatch::scopeVisibleTo().
     */
    public function view(User $user, IpcBatch $batch): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($user->isApprover() && in_array($batch->current_stage, IpcBatch::APPROVER_VISIBLE_STAGES, true)) {
            return true;
        }

        return $user->isMicrobiologist() && $batch->hasFinishedStage() && $batch->finishedCheck()->exists();
    }

    /** The Finished Good page: Staff edit it, a Microbiologist opens it read-only to see the context. */
    public function viewFinished(User $user, IpcBatch $batch): bool
    {
        return $user->isStaff()
            || ($user->isMicrobiologist() && $batch->hasFinishedStage() && $batch->finishedCheck()->exists());
    }

    /**
     * Fill/correct the Micro Check block. Opens once Finished Good is finalized (Finished Good is
     * the Staff's responsibility, Micro Check comes after it) and locks when the Microbiologist
     * presses "Selesaikan" on Micro Check itself.
     */
    public function recordMicroCheck(User $user, IpcBatch $batch): bool
    {
        if (! $user->isMicrobiologist() || ! $batch->hasFinishedStage()) {
            return false;
        }

        $finishedCheck = $batch->finishedCheck;
        $microCheck = $batch->microCheck;

        return $finishedCheck?->completed_at !== null && $microCheck?->completed_at === null;
    }

    /** Printed reports: Staff/Admin always, Approver once the batch reached Approval. */
    public function print(User $user, IpcBatch $batch): bool
    {
        return $user->isStaff()
            || ($user->isApprover() && in_array($batch->current_stage, IpcBatch::APPROVER_VISIBLE_STAGES, true));
    }
}
