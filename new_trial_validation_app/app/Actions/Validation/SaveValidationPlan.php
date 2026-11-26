<?php

namespace App\Actions\Validation;

use App\Actions\Notifications\CreateNotification;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationPlan;
use Illuminate\Support\Carbon;

/**
 * Weekly trial schedule request (PIE -> PPIC). The week is normalised to the
 * Monday of the ISO week. PPIC confirms with an optional note; the requester
 * (or Admin) can withdraw a request that is not confirmed yet.
 */
class SaveValidationPlan
{
    public function request(ValidationCase $case, string $week, ?string $notes, User $user): ValidationPlan
    {
        $case->loadMissing('product');

        $plan = ValidationPlan::create([
            'case_id' => $case->id,
            'product_id' => $case->product_id,
            'product_name' => (string) ($case->product->product_name ?? $case->product_name),
            'week_start' => Carbon::parse($week)->startOfWeek(),
            'status' => 'Requested',
            'notes' => $notes,
            'requested_by_user_id' => $user->id,
            'requested_by_name' => $user->name,
        ]);

        $this->log($user, 'CREATE', $plan, $case);

        // Tell PPIC; never block the flow if notification fails (CreateNotification never throws).
        app(CreateNotification::class)([
            'department_target' => 'PPIC',
            'title' => 'Validation Plan Requested',
            'message' => $case->case_no.' - '.$plan->product_name.' - '.$plan->week_start->format('Y-m-d'),
            'type' => 'validation_plan',
        ]);

        return $plan;
    }

    public function confirm(ValidationPlan $plan, ?string $note, User $user): ValidationPlan
    {
        $plan->status = 'Confirmed';
        $plan->ppic_note = $note;
        $plan->fill([
            'confirmed_by_user_id' => $user->id,
            'confirmed_by_name' => $user->name,
            'confirmed_at' => Carbon::now(),
        ])->save();

        $this->log($user, 'CONFIRM', $plan, $plan->case);

        return $plan;
    }

    public function cancel(ValidationPlan $plan, User $user): ValidationPlan
    {
        $plan->status = 'Cancelled';
        $plan->save();

        $this->log($user, 'CANCEL', $plan, $plan->case);

        return $plan;
    }

    private function log(User $user, string $action, ValidationPlan $plan, ValidationCase $case): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'VALIDATION_PLAN',
            'record_id' => (string) $plan->id,
            'record_label' => $case->case_no,
            'old_data' => null,
            'new_data' => json_encode(['week_start' => $plan->week_start->toDateString(), 'status' => $plan->status]),
        ]);
    }
}
