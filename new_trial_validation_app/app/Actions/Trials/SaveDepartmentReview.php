<?php

namespace App\Actions\Trials;

use App\Actions\Notifications\CreateNotification;
use App\Mail\TrialApprovalRequestedMail;
use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Port of the /review/{id}/save save block in the legacy app's
 * public/index.php:810-856 — a single department's review comment on a
 * trial currently In Review. TrialReviewPolicy::update() already confirmed
 * the review is the active one (right round, trial still eligible, $user
 * reviews for its department) before this runs.
 *
 * Two paths, branched on the review's current status:
 * - Pending (first submission): marks the review Reviewed, then recomputes
 *   the round's remaining Pending departments — if none remain, promotes the
 *   trial to Ready for Approval and notifies the assigned approver (or
 *   Admin/Manager QAC generically); otherwise just updates pending_with to
 *   whatever's left, matching legacy.
 * - Reviewed (a revision, allowed up to TrialReview::MAX_EDITS times per
 *   TrialReviewPolicy::update()): just updates the comment and bumps
 *   edit_count — does NOT re-run the round-completion/notification logic
 *   above, since that already happened once and re-firing it on every edit
 *   would re-send "Trial Waiting Final Approval" notifications needlessly.
 */
class SaveDepartmentReview
{
    public function __invoke(TrialReview $review, string $comment, User $reviewer): Trial
    {
        if ($review->status === 'Reviewed') {
            return $this->editReview($review, $comment, $reviewer);
        }

        $becameReadyForApproval = false;

        $trial = DB::transaction(function () use ($review, $comment, $reviewer, &$becameReadyForApproval) {
            $review->status = 'Reviewed';
            $review->reviewer_name = $reviewer->name ?: $reviewer->email;
            $review->reviewer_email = $reviewer->email;
            $review->comment = $comment;
            $review->reviewed_at = Carbon::now();
            $review->save();

            $this->closeAliasSiblings($review);

            $trial = Trial::whereNull('deleted_at')->findOrFail($review->trial_id);
            $round = $trial->currentReviewRound();

            $becameReadyForApproval = $this->recomputeRound($trial);

            ActivityLog::create([
                'user_id' => $reviewer->id,
                'user_name' => $reviewer->name,
                'user_role' => $reviewer->role,
                'action' => 'SUBMIT_REVIEW',
                'module' => 'REVIEW',
                'record_id' => (string) $review->id,
                'record_label' => $trial->trial_code.' '.$review->department,
                'old_data' => null,
                'new_data' => json_encode(['department' => $review->department, 'round' => $round, 'comment' => $comment]),
            ]);

            return $trial;
        });

        if ($becameReadyForApproval) {
            $this->notifyReadyForApproval($trial);
        }

        return $trial;
    }

    /**
     * Repairs a round left stuck by a same-department alias duplicate (a
     * Pending "PRD" row next to an already-Reviewed "PROD" one, from before
     * closeAliasSiblings() existed): closes the Pending sibling(s) with the
     * Reviewed row's data, then advances the trial exactly like a normal
     * review save would. Used by the trials:repair-review-aliases command.
     *
     * @return int number of Pending alias rows closed
     */
    public function repairAliasDuplicates(TrialReview $reviewed): int
    {
        $becameReadyForApproval = false;

        [$closed, $trial] = DB::transaction(function () use ($reviewed, &$becameReadyForApproval) {
            $closed = $this->closeAliasSiblings($reviewed);
            $trial = Trial::whereNull('deleted_at')->find($reviewed->trial_id);

            if ($closed > 0 && $trial && $trial->progress_status === 'In Review') {
                $becameReadyForApproval = $this->recomputeRound($trial);
            }

            return [$closed, $trial];
        });

        if ($becameReadyForApproval && $trial) {
            $this->notifyReadyForApproval($trial);
        }

        return $closed;
    }

    /**
     * Marks any still-Pending row for the same logical department (stored
     * under an alias code, see TrialReview::scopeAliasSiblingsOf()) as
     * Reviewed with this review's data — one department, one review.
     */
    private function closeAliasSiblings(TrialReview $review): int
    {
        return TrialReview::query()
            ->aliasSiblingsOf($review->trial_id, $review->review_round, $review->department, $review->id)
            ->where('status', 'Pending')
            ->update([
                'status' => 'Reviewed',
                'reviewer_name' => $review->reviewer_name,
                'reviewer_email' => $review->reviewer_email,
                'comment' => $review->comment,
                'reviewed_at' => $review->reviewed_at,
            ]);
    }

    /**
     * Recomputes the round's remaining Pending departments: promotes the
     * trial to Ready for Approval once none remain, otherwise updates
     * pending_with. Returns whether the trial was just promoted.
     */
    private function recomputeRound(Trial $trial): bool
    {
        $pendingDepartments = TrialReview::query()
            ->where('trial_id', $trial->id)
            ->where('review_round', $trial->currentReviewRound())
            ->where('status', 'Pending')
            ->orderBy('department')
            ->pluck('department')
            ->all();

        $promoted = false;
        if (! $pendingDepartments) {
            $approver = $trial->approver_user_id ? User::find($trial->approver_user_id) : null;
            $pendingWith = trim((string) ($approver->name ?? '')) ?: ($approver->email ?? 'Manager QAC');

            $trial->progress_status = 'Ready for Approval';
            $trial->current_step = 'Approval';
            $trial->pending_with = $pendingWith;
            $promoted = true;
        } else {
            $trial->pending_with = implode(',', $pendingDepartments);
        }

        $trial->save();

        return $promoted;
    }

    private function notifyReadyForApproval(Trial $trial): void
    {
        $approver = $trial->approver_user_id ? User::find($trial->approver_user_id) : null;

        (new CreateNotification)([
            'user_id' => $approver?->id,
            'role_target' => $approver ? null : 'Manager QAC',
            'trial_id' => $trial->id,
            'title' => 'Trial Waiting Final Approval',
            'message' => "Trial {$trial->trial_code} - {$trial->product_name} sudah selesai direview dan menunggu final approval.",
            'type' => 'approval',
        ]);

        (new CreateNotification)([
            'role_target' => 'Admin',
            'trial_id' => $trial->id,
            'title' => 'Trial Waiting Final Approval',
            'message' => "Trial {$trial->trial_code} - {$trial->product_name} sudah selesai direview dan menunggu final approval.",
            'type' => 'approval',
        ]);

        $this->emailApprover($trial, $approver);
    }

    /**
     * Email must never block the review-save workflow, matching the
     * never-throw invariant CreateNotification already relies on.
     */
    private function emailApprover(Trial $trial, ?User $approver): void
    {
        if (! $approver || ! $approver->email) {
            return;
        }

        try {
            Mail::to($approver->email)->send(new TrialApprovalRequestedMail(
                trial: $trial,
                approverName: $approver->name ?: $approver->email,
                approvalUrl: route('trials.report.show', $trial->id),
            ));
        } catch (Throwable) {
            // Email delivery must never block the main workflow.
        }
    }

    private function editReview(TrialReview $review, string $comment, User $reviewer): Trial
    {
        return DB::transaction(function () use ($review, $comment, $reviewer) {
            $review->reviewer_name = $reviewer->name ?: $reviewer->email;
            $review->reviewer_email = $reviewer->email;
            $review->comment = $comment;
            $review->reviewed_at = Carbon::now();
            $review->edit_count = $review->edit_count + 1;
            $review->save();

            $trial = Trial::whereNull('deleted_at')->findOrFail($review->trial_id);

            ActivityLog::create([
                'user_id' => $reviewer->id,
                'user_name' => $reviewer->name,
                'user_role' => $reviewer->role,
                'action' => 'EDIT_REVIEW',
                'module' => 'REVIEW',
                'record_id' => (string) $review->id,
                'record_label' => $trial->trial_code.' '.$review->department,
                'old_data' => null,
                'new_data' => json_encode([
                    'department' => $review->department,
                    'round' => $review->review_round,
                    'comment' => $comment,
                    'edit_count' => $review->edit_count,
                ]),
            ]);

            return $trial;
        });
    }
}
