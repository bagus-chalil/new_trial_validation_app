<?php

namespace App\Actions\Trials;

use App\Mail\TrialLineConfigurationSignOffRequestedMail;
use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Upserts the *current* (is_locked=false) Line Configuration Report row for
 * a trial. Authorization for *when* this is allowed at all (blocked once
 * the report has been submitted into the approval chain — see
 * TrialLineConfigurationReport::isSubmittedForApproval() — unless the actor
 * is Admin) lives in SaveTrialLineConfigurationReportRequest::authorize(),
 * not here; this action just performs the plain save.
 *
 * Assigning a new Approved(PIE)/Checked(PROD) user (approved_pie_user_id/
 * checked_prod_user_id changing to a different, non-null value) emails that
 * user a link to the trial's Report Summary page. This action never sets
 * approved_pie/checked_prod/return_prod itself — those are each their own
 * dedicated, auto-stamped action (MarkTrialLineConfigurationReportSignOff /
 * ReturnTrialLineConfigurationReport), so the date they record is a real
 * "I clicked this" timestamp, not whatever the form-filler happened to type.
 */
class SaveTrialLineConfigurationReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Trial $trial, array $data, User $user): TrialLineConfigurationReport
    {
        $existing = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->first();
        $isNew = $existing === null;

        $previousApprovedPieUserId = $existing?->approved_pie_user_id;
        $previousCheckedProdUserId = $existing?->checked_prod_user_id;

        $report = $existing ?? new TrialLineConfigurationReport(['trial_id' => $trial->id, 'version' => 1]);
        $report->fill([...$data, 'updated_by_user_id' => $user->id]);
        $report->save();

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

        $this->notifyIfNewlyAssigned($trial, $report, 'approved_pie_user_id', $previousApprovedPieUserId, 'Approved (PIE)');
        $this->notifyIfNewlyAssigned($trial, $report, 'checked_prod_user_id', $previousCheckedProdUserId, 'Checked (PROD)');

        return $report;
    }

    /**
     * Emails must never block the save, matching the never-throw invariant
     * every other notification-sending action in this app relies on.
     */
    private function notifyIfNewlyAssigned(Trial $trial, TrialLineConfigurationReport $report, string $column, ?int $previousUserId, string $fieldLabel): void
    {
        $currentUserId = $report->{$column};

        if (! $currentUserId || (int) $currentUserId === (int) $previousUserId) {
            return;
        }

        try {
            $assignee = User::query()->where('id', $currentUserId)->where('is_active', 1)->first();

            if (! $assignee || ! $assignee->email) {
                return;
            }

            Mail::to($assignee->email)->send(new TrialLineConfigurationSignOffRequestedMail(
                trial: $trial,
                assigneeName: $assignee->name ?: $assignee->email,
                fieldLabel: $fieldLabel,
                reportUrl: route('trials.report.show', $trial->id),
            ));
        } catch (Throwable) {
            // Email delivery must never block the main workflow.
        }
    }
}
