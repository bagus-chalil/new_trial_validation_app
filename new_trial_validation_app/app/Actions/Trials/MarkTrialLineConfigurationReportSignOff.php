<?php

namespace App\Actions\Trials;

use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * One eligible user's one-click confirmation of the report's *current* stage
 * — PROD acknowledge first, then a PIE Leader approves (see
 * App\Policies\TrialLineConfigurationReportPolicy for who may call this).
 * Always stamps the acting user's own name and Carbon::now(): nobody is
 * assigned in advance any more (2026-11-25), so this is the record of who
 * really signed, with a real, automatic timestamp.
 *
 * When PROD acknowledges, every PIE Leader who may approve is notified (the
 * PROD signers were notified at submit time, see
 * SaveTrialLineConfigurationReport).
 *
 * `$comment` is optional and freely typed by the confirming user alongside
 * the same click — unlike Return's mandatory reason, this is just a note.
 */
class MarkTrialLineConfigurationReportSignOff
{
    /**
     * @param  'approved_pie'|'checked_prod'  $field
     */
    public function __invoke(Trial $trial, TrialLineConfigurationReport $report, string $field, User $user, ?string $comment = null): TrialLineConfigurationReport
    {
        $report->{$field} = true;
        $report->{"{$field}_by"} = $user->name ?: $user->email;
        $report->{"{$field}_at"} = Carbon::now();
        $report->{"{$field}_comment"} = $comment;
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
            'new_data' => json_encode([$field => true, 'comment' => $comment]),
        ]);

        if ($field === 'checked_prod') {
            (new NotifyLineConfigurationSigners)($trial, 'approved_pie');
        }

        return $report;
    }
}
