<?php

namespace App\Actions\Trials;

use App\Mail\TrialLineConfigurationReturnedMail;
use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialLineConfigurationReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Whoever's turn it currently is in the maker-checker chain (see
 * App\Policies\TrialLineConfigurationReportPolicy::returnReport()) can send
 * a Line Configuration Report back for revision instead of confirming their
 * stage. Unlike the plain edit form, this always stamps the acting user's
 * own name/Carbon::now() and requires a real reason (see
 * ReturnLineConfigurationReportRequest — minimum 5 words).
 *
 * Immediately locks the report *as it stood at the point of return* — this
 * is the historical record of exactly what was rejected and why — and
 * clones a fresh current version forward with the same content (production
 * standard, line configuration, header fields) but every sign-off field
 * cleared (approvals, assignments, return state all reset): a full new
 * review cycle, not a continuation of stale assignments that would
 * otherwise leave the report immediately re-locked
 * (TrialLineConfigurationReport::isSubmittedForApproval()) with no chance
 * for the maker to actually revise it.
 *
 * Also emails the report's drafter (updated_by_user_id — whoever last saved
 * its content) directly, regardless of whether Approved(PIE) or
 * Checked(PROD) did the returning — the notice always goes straight to the
 * drafter, never back through the sign-off chain.
 */
class ReturnTrialLineConfigurationReport
{
    public function __invoke(Trial $trial, TrialLineConfigurationReport $report, string $reason, User $user): TrialLineConfigurationReport
    {
        $returnedByStage = match ($report->currentApprovalStage()) {
            'approved_pie' => 'Approved (PIE)',
            'checked_prod' => 'Checked (PROD)',
            default => null,
        };

        $next = DB::transaction(function () use ($trial, $report, $reason, $user) {
            $report->return_prod = true;
            $report->return_prod_by = $user->name ?: $user->email;
            $report->return_prod_at = Carbon::now();
            $report->return_reason = $reason;
            $report->is_locked = true;
            $report->locked_at = Carbon::now();
            $report->save();

            $next = $report->replicate(['id', 'created_at', 'updated_at']);
            $next->version = $report->version + 1;
            $next->is_locked = false;
            $next->locked_at = null;
            $next->approved_pie = false;
            $next->approved_pie_by = null;
            $next->approved_pie_at = null;
            $next->approved_pie_user_id = null;
            $next->checked_prod = false;
            $next->checked_prod_by = null;
            $next->checked_prod_at = null;
            $next->checked_prod_user_id = null;
            $next->return_prod = false;
            $next->return_prod_by = null;
            $next->return_prod_at = null;
            $next->return_reason = null;
            $next->save();

            ActivityLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'action' => 'RETURN',
                'module' => 'LINE_CONFIG',
                'record_id' => (string) $report->id,
                'record_label' => $trial->trial_code.' Line Configuration Report',
                'old_data' => null,
                'new_data' => json_encode(['reason' => $reason]),
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'action' => 'NEW_VERSION',
                'module' => 'LINE_CONFIG',
                'record_id' => (string) $next->id,
                'record_label' => $trial->trial_code.' Line Configuration Report',
                'old_data' => json_encode(['locked_version' => $report->version]),
                'new_data' => json_encode(['new_version' => $next->version]),
            ]);

            return $next;
        });

        $this->notifyDrafter($trial, $report, $reason, $returnedByStage);

        return $next;
    }

    /**
     * Emails must never block the return itself, matching the never-throw
     * invariant every other notification-sending action in this app relies
     * on.
     */
    private function notifyDrafter(Trial $trial, TrialLineConfigurationReport $report, string $reason, ?string $returnedByStage): void
    {
        try {
            $drafter = $report->updatedBy;

            if (! $drafter || ! $drafter->email) {
                return;
            }

            Mail::to($drafter->email)->send(new TrialLineConfigurationReturnedMail(
                trial: $trial,
                drafterName: $drafter->name ?: $drafter->email,
                returnedByStage: $returnedByStage,
                reason: $reason,
                reportUrl: route('trials.report.show', $trial->id),
            ));
        } catch (Throwable) {
            // Email delivery must never block the main workflow.
        }
    }
}
