<?php

namespace App\Actions\Improvements;

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\User;

/** One `activity_logs` row with module IMPROVEMENT; `record_id` is the improvement id. */
class LogImprovementActivity
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function __invoke(User $user, string $action, Improvement $improvement, ?array $old = null, ?array $new = null): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'IMPROVEMENT',
            'record_id' => (string) $improvement->id,
            'record_label' => mb_substr($improvement->title, 0, 255),
            'old_data' => $old === null ? null : json_encode($old),
            'new_data' => $new === null ? null : json_encode($new),
        ]);
    }
}
