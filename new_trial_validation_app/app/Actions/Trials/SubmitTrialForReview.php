<?php

namespace App\Actions\Trials;

use App\Actions\Notifications\CreateNotification;
use App\Models\ActivityLog;
use App\Models\Trial;
use App\Models\TrialReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Port of the /trials/{id}/submit-review save block in the legacy app's
 * public/index.php:740-793 — wizard Step 6 (Review & Submit). Completeness
 * (CheckTrialCompleteness) and the departments/approver/reviewer assignments
 * themselves are validated by SubmitTrialForReviewRequest before this runs;
 * this action just performs the state transition. Upserts a Pending
 * trials_review row per selected department for the trial's current review
 * round (resetting any stale reviewer/comment data from a prior round,
 * matching legacy's ON DUPLICATE KEY UPDATE) — unlike legacy, each row is
 * assigned to one specific reviewer rather than leaving it open to anyone in
 * the department (see TrialReviewPolicy::update()) — moves the trial to In
 * Review, and notifies each assigned reviewer plus Admin.
 */
class SubmitTrialForReview
{
    /**
     * @param  list<string>  $departments
     * @param  array<string, int>  $reviewerUserIds  department => assigned reviewer's user id
     */
    public function __invoke(Trial $trial, array $departments, array $reviewerUserIds, User $approver, User $submittedBy): Trial
    {
        DB::transaction(function () use ($trial, $departments, $reviewerUserIds, $approver, $submittedBy) {
            $round = $trial->currentReviewRound();

            foreach ($departments as $department) {
                TrialReview::query()->updateOrCreate(
                    ['trial_id' => $trial->id, 'department' => $department, 'review_round' => $round],
                    [
                        'status' => 'Pending',
                        'is_required' => true,
                        'reviewer_user_id' => $reviewerUserIds[$department] ?? null,
                        'reviewer_name' => null,
                        'reviewer_email' => null,
                        'comment' => null,
                        'reviewed_at' => null,
                    ],
                );
            }

            $trial->progress_status = 'In Review';
            $trial->current_step = 'Review';
            $trial->pending_with = implode(',', $departments);
            $trial->approver_user_id = $approver->id;
            $trial->save();

            ActivityLog::create([
                'user_id' => $submittedBy->id,
                'user_name' => $submittedBy->name,
                'user_role' => $submittedBy->role,
                'action' => 'SUBMIT_REVIEW',
                'module' => 'REVIEW',
                'record_id' => (string) $trial->id,
                'record_label' => $trial->trial_code,
                'old_data' => null,
                'new_data' => json_encode(['round' => $round, 'departments' => $departments, 'reviewer_user_ids' => $reviewerUserIds, 'approver' => $approver->email]),
            ]);
        });

        foreach ($departments as $department) {
            $reviewerId = $reviewerUserIds[$department] ?? null;

            (new CreateNotification)([
                'user_id' => $reviewerId,
                'role_target' => $reviewerId ? null : 'Reviewer',
                'department_target' => $reviewerId ? null : $department,
                'trial_id' => $trial->id,
                'title' => 'New Trial Waiting for Review',
                'message' => "Trial {$trial->trial_code} - {$trial->product_name} membutuhkan review Anda.",
                'type' => 'review',
            ]);
        }

        (new CreateNotification)([
            'role_target' => 'Admin',
            'trial_id' => $trial->id,
            'title' => 'Trial Submitted for Review',
            'message' => "Trial {$trial->trial_code} - {$trial->product_name} dikirim ke review department: ".implode(', ', $departments).'.',
            'type' => 'info',
        ]);

        return $trial->fresh();
    }
}
