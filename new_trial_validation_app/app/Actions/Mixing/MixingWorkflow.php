<?php

namespace App\Actions\Mixing;

use App\Actions\Notifications\CreateNotification;
use App\Models\ActivityLog;
use App\Models\Mixing;
use App\Models\MixingReview;
use App\Models\User;
use App\Models\ValidationParameter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mixing approval flow, same pattern as the Trial (decision: Tahap 4 asked the
 * team and the answer was "same as Trial"): submit -> one review per selected
 * department -> final decision by the assigned approver with the user's own
 * password as e-signature. Notifications carry no trial_id (they are not
 * trials); the message names the MIX code.
 */
class MixingWorkflow
{
    /**
     * What is still missing before a Mixing may be submitted.
     *
     * @return list<string>
     */
    public function completeness(Mixing $mixing): array
    {
        $errors = [];

        foreach (['product_id' => 'product', 'product_type' => 'product_type', 'batch_no' => 'batch_no', 'mixing_date' => 'mixing_date'] as $field => $label) {
            if (blank($mixing->{$field})) {
                $errors[] = __('mixing.completeness.field', ['field' => __('mixing.fields.'.$label)]);
            }
        }

        $parameters = ValidationParameter::query()
            ->where('is_active', 1)->whereNull('deleted_at')
            ->where('product_type', $mixing->product_type)
            ->get(['id', 'parameter_name']);

        $results = $mixing->results()->get()->keyBy('parameter_id');

        foreach ($parameters as $parameter) {
            $row = $results->get($parameter->id);
            if ($row === null || $row->decision === '') {
                $errors[] = __('mixing.completeness.missing', ['name' => $parameter->parameter_name]);
            } elseif ($row->decision === 'NOT OK' && (blank($row->result_value) || blank($row->remark))) {
                $errors[] = __('mixing.completeness.not_ok', ['name' => $parameter->parameter_name]);
            }
        }

        return $errors;
    }

    /** @param list<string> $departments */
    public function submit(Mixing $mixing, array $departments, User $approver, User $by): Mixing
    {
        DB::transaction(function () use ($mixing, $departments, $approver, $by) {
            $round = $mixing->currentReviewRound();

            foreach ($departments as $department) {
                MixingReview::query()->updateOrCreate(
                    ['mixing_id' => $mixing->id, 'department' => $department, 'review_round' => $round],
                    ['status' => 'Pending', 'reviewer_user_id' => null, 'reviewer_name' => null, 'comment' => null, 'reviewed_at' => null],
                );
            }

            $mixing->progress_status = 'In Review';
            $mixing->current_step = 'Review';
            $mixing->pending_with = implode(',', $departments);
            $mixing->approver_user_id = $approver->id;
            $mixing->final_decision = null;
            $mixing->save();

            $this->log($by, 'SUBMIT_REVIEW', $mixing, ['round' => $round, 'departments' => $departments, 'approver' => $approver->email]);
        });

        foreach ($departments as $department) {
            (new CreateNotification)([
                'role_target' => 'Reviewer',
                'department_target' => $department,
                'title' => 'New Mixing Waiting for Review',
                'message' => "Mixing {$mixing->mixing_code} - {$mixing->product_name} membutuhkan review department Anda.",
                'type' => 'review',
            ]);
        }

        return $mixing->fresh() ?? $mixing;
    }

    public function review(MixingReview $review, string $comment, User $reviewer): Mixing
    {
        $mixing = $review->mixing;
        if ($mixing === null) {
            throw new \RuntimeException('Review without a Mixing.');
        }

        DB::transaction(function () use ($review, $mixing, $comment, $reviewer) {
            $review->status = 'Reviewed';
            $review->reviewer_user_id = $reviewer->id;
            $review->reviewer_name = $reviewer->name ?: $reviewer->email;
            $review->comment = $comment;
            $review->reviewed_at = Carbon::now();
            $review->save();

            $pending = MixingReview::query()
                ->where('mixing_id', $mixing->id)
                ->where('review_round', $review->review_round)
                ->where('status', 'Pending')
                ->pluck('department')
                ->all();

            if ($pending === []) {
                $approver = $mixing->approver_user_id ? User::query()->find($mixing->approver_user_id) : null;
                $mixing->progress_status = 'Ready for Approval';
                $mixing->current_step = 'Approval';
                $mixing->pending_with = $approver ? (string) ($approver->name ?: $approver->email) : 'Manager QAC';
            } else {
                $mixing->pending_with = implode(',', $pending);
            }
            $mixing->save();

            $this->log($reviewer, 'REVIEW', $mixing, ['department' => $review->department, 'remaining' => $pending]);
        });

        $mixing = $mixing->fresh() ?? $mixing;

        if ($mixing->progress_status === 'Ready for Approval') {
            (new CreateNotification)([
                'user_id' => $mixing->approver_user_id,
                'title' => 'Mixing Waiting Final Approval',
                'message' => "Mixing {$mixing->mixing_code} - {$mixing->product_name} menunggu approval Anda.",
                'type' => 'approval',
            ]);
        }

        return $mixing;
    }

    /** @param 'Approved'|'Need Revision'|'Rejected' $decision */
    public function decide(Mixing $mixing, string $decision, string $comment, User $approver): Mixing
    {
        $name = $approver->name ?: $approver->email;

        DB::transaction(function () use ($mixing, $decision, $comment, $name, $approver) {
            $now = Carbon::now();
            $mixing->approval_comment = $comment;
            $mixing->final_decision = $decision;
            $mixing->pending_with = '';

            if ($decision === 'Approved') {
                $mixing->progress_status = 'Approved';
                $mixing->approved_by = $name;
                $mixing->approved_at = $now;
            } elseif ($decision === 'Need Revision') {
                $mixing->progress_status = 'Need Revision';
                $mixing->current_step = 'Revision';
                $mixing->pending_with = 'Staff';
                $mixing->revision_no = $mixing->revision_no + 1;
                $mixing->rejected_by = $name;
                $mixing->rejected_at = $now;
            } else {
                $mixing->progress_status = 'Rejected';
                $mixing->current_step = 'Closed';
                $mixing->rejected_by = $name;
                $mixing->rejected_at = $now;
            }
            $mixing->save();

            $this->log($approver, strtoupper(str_replace(' ', '_', $decision)), $mixing, ['decision' => $decision, 'comment' => $comment]);
        });

        $owner = User::query()->where('is_active', 1)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim((string) $mixing->created_by))])
            ->first();

        if ($owner !== null) {
            (new CreateNotification)([
                'user_id' => $owner->id,
                'title' => 'Mixing '.$decision,
                'message' => "Mixing {$mixing->mixing_code} - {$mixing->product_name}: {$decision}.",
                'type' => 'info',
            ]);
        }

        return $mixing->fresh() ?? $mixing;
    }

    /** @param array<string, mixed> $data */
    private function log(User $user, string $action, Mixing $mixing, array $data): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'MIXING_APPROVAL',
            'record_id' => (string) $mixing->id,
            'record_label' => $mixing->mixing_code,
            'old_data' => null,
            'new_data' => json_encode($data),
        ]);
    }
}
