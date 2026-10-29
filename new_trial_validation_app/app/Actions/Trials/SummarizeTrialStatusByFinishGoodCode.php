<?php

namespace App\Actions\Trials;

use App\Models\Trial;
use Carbon\CarbonInterface;

/**
 * Read-only roll-up of every active trial per Finish Good code, for sibling apps (ipc_app) asking
 * "has this FG code been trialled yet?". One FG code can have many trials, so each is summarised:
 * `approved` once any trial is Approved, else `in_progress` while any is still moving
 * (Draft/In Review/Ready for Approval/Need Revision), else `rejected` (only Rejected trials).
 * Trials without a real FG code (blank / 'N/A') can't be matched and are left out.
 */
class SummarizeTrialStatusByFinishGoodCode
{
    public const STATUS_APPROVED = 'approved';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @return array<string, array<string, mixed>> keyed by finish_good_code
     */
    public function __invoke(): array
    {
        $trials = Trial::query()
            ->whereNull('deleted_at')
            ->whereNotNull('finish_good_code')
            ->whereNotIn('finish_good_code', ['', 'N/A'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'trial_code', 'finish_good_code', 'product_name', 'progress_status', 'current_step', 'pending_with', 'approved_at', 'created_at']);

        $summary = [];

        foreach ($trials->groupBy('finish_good_code') as $fgCode => $group) {
            /** @var Trial $latest */
            $latest = $group->first();
            $lastApproved = $group->whereNotNull('approved_at')->max('approved_at');

            $summary[(string) $fgCode] = [
                'status' => $this->statusFor($group->pluck('progress_status')->all()),
                'product_name' => $latest->product_name,
                'trial_count' => $group->count(),
                'approved_count' => $group->where('progress_status', 'Approved')->count(),
                'latest_trial_id' => $latest->id,
                'latest_trial_code' => $latest->trial_code,
                'latest_progress_status' => $latest->progress_status,
                'latest_step' => $latest->current_step,
                'pending_with' => $latest->pending_with,
                'last_approved_at' => $lastApproved instanceof CarbonInterface ? $lastApproved->toIso8601String() : null,
            ];
        }

        return $summary;
    }

    /** @param  array<int, string>  $progressStatuses */
    private function statusFor(array $progressStatuses): string
    {
        if (in_array('Approved', $progressStatuses, true)) {
            return self::STATUS_APPROVED;
        }

        if (array_diff($progressStatuses, ['Rejected']) !== []) {
            return self::STATUS_IN_PROGRESS;
        }

        return self::STATUS_REJECTED;
    }
}
