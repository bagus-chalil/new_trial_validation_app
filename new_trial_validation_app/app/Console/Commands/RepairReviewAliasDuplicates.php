<?php

namespace App\Console\Commands;

use App\Actions\Trials\SaveDepartmentReview;
use App\Models\TrialReview;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off repair for trials stuck In Review because one department has two
 * rows in the current round under alias codes (e.g. legacy "PRD" Pending
 * next to "PROD" Reviewed — the report page shows them as one line, but the
 * Pending row keeps the trial in the reviewer's Need Review list and blocks
 * the round from completing). Dry run unless --apply is passed.
 */
#[Signature('trials:repair-review-aliases {--apply : Actually write the fix (default is a dry run)}')]
#[Description('Close Pending review rows whose same-department alias row is already Reviewed')]
class RepairReviewAliasDuplicates extends Command
{
    public function handle(SaveDepartmentReview $action): int
    {
        $apply = (bool) $this->option('apply');

        $reviewed = TrialReview::query()
            ->join('trials_header as h', 'h.id', '=', 'trials_review.trial_id')
            ->whereNull('h.deleted_at')
            ->where('h.progress_status', 'In Review')
            ->whereRaw('trials_review.review_round = h.revision_no + 1')
            ->where('trials_review.status', 'Reviewed')
            ->select('trials_review.*', 'h.trial_code')
            ->orderBy('trials_review.trial_id')
            ->get();

        $rows = [];
        foreach ($reviewed as $review) {
            $pending = TrialReview::query()
                ->aliasSiblingsOf($review->trial_id, $review->review_round, $review->department, $review->id)
                ->where('status', 'Pending')
                ->pluck('department')
                ->all();

            if (! $pending) {
                continue;
            }

            $rows[] = [$review->getAttribute('trial_code'), $review->department.' (Reviewed)', implode(', ', $pending).' (Pending)'];

            if ($apply) {
                $action->repairAliasDuplicates($review);
            }
        }

        if (! $rows) {
            $this->info('Tidak ada review ganda (alias department) yang perlu diperbaiki.');

            return self::SUCCESS;
        }

        $this->table(['Trial', 'Sudah review', 'Baris ganda'], $rows);
        $this->info($apply
            ? count($rows).' trial diperbaiki.'
            : count($rows).' trial terdeteksi. Jalankan ulang dengan --apply untuk memperbaiki.');

        return self::SUCCESS;
    }
}
