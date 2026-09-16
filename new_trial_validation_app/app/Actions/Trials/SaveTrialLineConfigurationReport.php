<?php

namespace App\Actions\Trials;

use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;

/**
 * Upserts the one Line Configuration Report row for a trial (unique
 * trial_id — see the 2026-09-16 migration). Always a plain save, never
 * blocked by the trial's status or any prior submission: the user explicitly
 * asked for this form to stay editable indefinitely (no lock), so unlike
 * SaveDepartmentReview there is no Pending/Reviewed branch and no edit-count
 * bookkeeping here.
 */
class SaveTrialLineConfigurationReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Trial $trial, array $data, User $user): TrialLineConfigurationReport
    {
        $isNew = ! TrialLineConfigurationReport::where('trial_id', $trial->id)->exists();

        $report = TrialLineConfigurationReport::updateOrCreate(
            ['trial_id' => $trial->id],
            [...$data, 'updated_by_user_id' => $user->id],
        );

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $isNew ? 'CREATE' : 'UPDATE',
            'module' => 'LINE_CONFIG',
            'record_id' => (string) $report->id,
            'record_label' => $trial->trial_code.' Line Configuration Report',
            'old_data' => null,
            'new_data' => json_encode($data),
        ]);

        return $report;
    }
}
