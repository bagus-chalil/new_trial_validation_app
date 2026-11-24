<?php

namespace App\Actions\Toolings;

use App\Models\ActivityLog;
use App\Models\Tooling;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Records one Check Tooling step twice: as an entry of the request's own
 * timeline (`history_log`, shown on the detail page next to the imported
 * legacy history) and as an `activity_logs` row (module TOOLING, record_id =
 * tooling id) for the system-wide audit trail. Call before saving the model.
 */
class LogToolingActivity
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function __invoke(User $user, string $action, Tooling $tooling, ?string $note = null, ?array $old = null, ?array $new = null): void
    {
        $history = $tooling->history_log ?? [];
        $history[] = [
            'at' => Carbon::now()->toDateTimeString(),
            'by' => $user->name,
            'status' => $tooling->status,
            'action' => $action,
            'note' => (string) ($note ?? ''),
        ];
        $tooling->history_log = $history;
        $tooling->updated_by_name = $user->name;

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'TOOLING',
            'record_id' => $tooling->id ? (string) $tooling->id : null,
            'record_label' => mb_substr(trim($tooling->npd_no.' '.$tooling->product_name), 0, 255),
            'old_data' => $old === null ? null : json_encode($old),
            'new_data' => $new === null ? null : json_encode($new),
        ]);
    }
}
