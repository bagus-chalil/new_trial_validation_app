<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ImRevision;
use App\Models\User;

/** One audit line per IM action (module IM_MASTER), like the other v2.0 modules. */
final class ImLog
{
    /** @param array<string, mixed> $data */
    public static function write(User $user, string $action, ImRevision $revision, array $data = []): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'IM_MASTER',
            'record_id' => (string) $revision->id,
            'record_label' => $revision->im_number,
            'old_data' => null,
            'new_data' => json_encode($data + ['status' => $revision->status, 'revision_no' => $revision->revision_no]),
        ]);
    }
}
