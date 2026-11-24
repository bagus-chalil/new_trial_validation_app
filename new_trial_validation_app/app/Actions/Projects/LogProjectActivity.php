<?php

namespace App\Actions\Projects;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Writes one `activity_logs` row with module PROJECT. `record_id` is always
 * the project id so a project's whole history is one query.
 */
class LogProjectActivity
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function __invoke(User $user, string $action, int $projectId, string $label, ?array $old = null, ?array $new = null): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'PROJECT',
            'record_id' => (string) $projectId,
            'record_label' => $label,
            'old_data' => $old === null ? null : json_encode($old),
            'new_data' => $new === null ? null : json_encode($new),
        ]);
    }
}
