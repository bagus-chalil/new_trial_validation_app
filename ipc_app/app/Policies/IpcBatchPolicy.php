<?php

namespace App\Policies;

use App\Models\IpcBatch;
use App\Models\User;

class IpcBatchPolicy
{
    /**
     * Any Staff (or Admin) can continue any batch's stage data — batches are shift-worked, not
     * owned by whoever started them, so a batch a Staff started in one shift must be editable by
     * a different Staff finishing it in the next shift. Approver is deliberately excluded: that
     * role only approves (see the separate `approve-ipc` gate), not edit stage data.
     */
    public function update(User $user, IpcBatch $batch): bool
    {
        return $user->isStaff();
    }
}
