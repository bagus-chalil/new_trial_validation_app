<?php

namespace App\Policies;

use App\Models\IpcBatch;
use App\Models\User;

class IpcBatchPolicy
{
    public function update(User $user, IpcBatch $batch): bool
    {
        return $user->isAdmin() || $batch->created_by === $user->id;
    }
}
