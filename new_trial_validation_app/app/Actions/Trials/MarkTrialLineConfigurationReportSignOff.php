<?php

namespace App\Actions\Trials;

use App\Mail\TrialLineConfigurationSignOffRequestedMail;
use App\Models\ActivityLog;
use App\Models\LineConfigurationLane;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
 *
 * The approval chain is step-by-step (see
 * TrialLineConfigurationReport::currentApprovalStage()), so the Checked(PROD)
 * assignee's email is deliberately deferred to *this* moment — right after
 * Approved(PIE) is confirmed — rather than firing immediately at assignment
 * time (SaveTrialLineConfigurationReport only ever emails the Approved(PIE)
 * assignee immediately, since that stage is always first).
 *
 * `$comment` (added 2026-09-24, user request) is optional and freely typed
 * by the confirming user alongside the same click — unlike Return's
 * mandatory reason, this is just a note, not a revision request.
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

        if ($field === 'approved_pie') {
            $this->notifyCheckedProdStageReady($trial, $report);
        }

        return $report;
    }

    /**
     * Emails must never block the save, matching the never-throw invariant
     * every other notification-sending action in this app relies on.
     */
    private function notifyCheckedProdStageReady(Trial $trial, TrialLineConfigurationReport $report): void
    {
        if (! $report->checked_prod_user_id) {
            return;
        }

        try {
            $assignee = User::query()->where('id', $report->checked_prod_user_id)->where('is_active', 1)->first();

            if (! $assignee || ! $assignee->email) {
                return;
            }

            Mail::to($assignee->email)->send(new TrialLineConfigurationSignOffRequestedMail(
                trial: $trial,
                assigneeName: $assignee->name ?: $assignee->email,
                fieldLabel: LineConfigurationLane::label('checked_prod', 'Checked (PROD)'),
                reportUrl: route('trials.report.show', $trial->id),
            ));
        } catch (Throwable) {
            // Email delivery must never block the main workflow.
        }
    }
}
