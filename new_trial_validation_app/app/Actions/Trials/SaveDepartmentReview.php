<?php

namespace App\Actions\Trials;

use App\Actions\Notifications\CreateNotification;
use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

            $trial = Trial::whereNull('deleted_at')->findOrFail($review->trial_id);
            $round = $trial->currentReviewRound();

            $pendingDepartments = TrialReview::query()
                ->where('trial_id', $trial->id)
                ->where('review_round', $round)
                ->where('status', 'Pending')
                ->orderBy('department')
                ->pluck('department')
                ->all();

            if (! $pendingDepartments) {
                $approver = $trial->approver_user_id ? User::find($trial->approver_user_id) : null;
                $pendingWith = trim((string) ($approver->name ?? '')) ?: ($approver->email ?? 'Manager QAC');

                $trial->progress_status = 'Ready for Approval';
                $trial->current_step = 'Approval';
                $trial->pending_with = $pendingWith;
                $becameReadyForApproval = true;
            } else {
                $trial->pending_with = implode(',', $pendingDepartments);
            }

            $trial->save();

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
        }

        return $trial;
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
