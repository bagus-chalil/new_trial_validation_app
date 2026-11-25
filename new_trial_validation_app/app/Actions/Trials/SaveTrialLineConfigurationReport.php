<?php

namespace App\Actions\Trials;

use App\Models\ActivityLog;
use App\Models\LineConfigurationLane;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Upserts the *current* (is_locked=false) Line Configuration Report row for
 * a trial. Authorization for *when* this is allowed at all (blocked once
 * the report has been submitted into the approval chain — see
 * TrialLineConfigurationReport::isSubmittedForApproval() — unless the actor
 * is Admin) lives in SaveTrialLineConfigurationReportRequest::authorize(),
 * not here; this action just performs the plain save.
 *
 * `$submit` (the "Simpan & Kirim Approval" button) stamps `submitted_at`,
 * which is what puts the report into the chain and locks it. Since
 * 2026-11-25 nobody is picked on the form: the first stage is PROD
 * acknowledge, so every user who may sign it is notified right away (once —
 * an Admin re-saving an already-submitted report does not re-notify). The
 * second stage (PIE Leader) is notified later, by
 * MarkTrialLineConfigurationReportSignOff, when PROD has acknowledged. This
 * action never sets approved_pie/checked_prod/return_prod itself — those are
 * each their own dedicated, auto-stamped action, so the date they record is a
 * real "I clicked this" timestamp.
 */
class SaveTrialLineConfigurationReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Trial $trial, array $data, User $user, bool $submit = false): TrialLineConfigurationReport
    {
        $existing = TrialLineConfigurationReport::where('trial_id', $trial->id)->where('is_locked', false)->first();
        $isNew = $existing === null;
        $newlySubmitted = $submit && ! ($existing?->isSubmittedForApproval() ?? false);

        $report = $existing ?? new TrialLineConfigurationReport(['trial_id' => $trial->id, 'version' => 1]);
        $labels = LineConfigurationLane::labels();
        $report->fill([
            ...$data,
            'updated_by_user_id' => $user->id,
            // Snapshot the lane's current label on every save while this
            // row is still the editable/current one — once it's later
            // locked (Return), this stamped value freezes with it, so a
            // subsequent lane rename never silently relabels an already-
            // locked historical version (Phase 3 of the RBAC/Team-master
            // redesign — see LineConfigurationLane).
            'approved_pie_label' => $labels['approved_pie'],
            'checked_prod_label' => $labels['checked_prod'],
        ]);
        if ($newlySubmitted) {
            $report->submitted_at = Carbon::now();
        }
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

        if ($newlySubmitted) {
            (new NotifyLineConfigurationSigners)($trial, 'checked_prod');
        }

        return $report;
    }
}
