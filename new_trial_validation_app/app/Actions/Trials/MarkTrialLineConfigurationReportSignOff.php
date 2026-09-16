<?php

namespace App\Actions\Trials;

use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The assigned Approved(PIE)/Checked(PROD) user's own one-click confirmation
 * on a trial's *current* Line Configuration Report version — see
 * App\Policies\TrialLineConfigurationReportPolicy for who may call this.
 * Always stamps the acting user's own name and Carbon::now(), unlike every
 * other field on this form (which stays freely typed by whoever's editing):
 * the "who" was already fixed at assignment time
 * (SaveTrialLineConfigurationReport's *_user_id), so this action just
 * confirms that person really did click it, with a real, automatic
 * timestamp — matching the user's explicit "otomatis tanggal record" request.
 */
class MarkTrialLineConfigurationReportSignOff
{
    /**
     * @param  'approved_pie'|'checked_prod'  $field
     */
    public function __invoke(Trial $trial, TrialLineConfigurationReport $report, string $field, User $user): TrialLineConfigurationReport
    {
        $report->{$field} = true;
        $report->{"{$field}_by"} = $user->name ?: $user->email;
        $report->{"{$field}_at"} = Carbon::now();
        $report->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $field === 'approved_pie' ? 'APPROVE_PIE' : 'CHECK_PROD',
            'module' => 'LINE_CONFIG',
            'record_id' => (string) $report->id,
            'record_label' => $trial->trial_code.' Line Configuration Report',
            'old_data' => null,
            'new_data' => json_encode([$field => true]),
        ]);

        return $report;
    }
}
