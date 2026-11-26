<?php

namespace App\Actions\ImMaster;

use App\Actions\Notifications\CreateNotification;
use App\Models\ImNumber;
use App\Models\ImRevision;
use App\Models\ImSignoff;
use App\Models\User;
use App\Support\ImLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * QA sign-off chain of an IM revision, all inside QA: Prepared by (the QA
 * author, signs when submitting) -> Review by (one or more QA reviewers) ->
 * Approved by (one QA approver). Each signature is the user's own password,
 * checked by the controller, like Trial / Mixing approval. Approval releases
 * the revision through ImRelease.
 */
class ImWorkflow
{
    public function __construct(private readonly ImRelease $release) {}

    /**
     * What is still missing before the revision may be submitted.
     *
     * @return list<string>
     */
    public function completeness(ImRevision $revision): array
    {
        $errors = [];

        foreach (['description', 'line_label'] as $field) {
            if (blank($revision->{$field})) {
                $errors[] = __('im_master.completeness.field', ['field' => __('im_master.fields.'.$field)]);
            }
        }
        foreach (['issue_date', 'effective_from'] as $field) {
            if ($revision->{$field} === null) {
                $errors[] = __('im_master.completeness.field', ['field' => __('im_master.fields.'.$field)]);
            }
        }
        if ($revision->revision_no > 1 && blank($revision->revision_reason)) {
            $errors[] = __('im_master.completeness.field', ['field' => __('im_master.fields.revision_reason')]);
        }

        $params = $revision->parameters()->get()->groupBy('section');

        foreach (['bom', 'weight', 'surveillance_initial'] as $section) {
            if (($params[$section] ?? collect())->isEmpty()) {
                $errors[] = __('im_master.completeness.section', ['section' => __('im_master.sections.'.$section)]);
            }
        }

        foreach ($params['weight'] ?? [] as $row) {
            if ($row->value_min === null || $row->value_max === null || (float) $row->value_min > (float) $row->value_max) {
                $errors[] = __('im_master.completeness.weight_limits', ['name' => $row->name]);
            }
        }

        if ($revision->evidence()->count() === 0) {
            $errors[] = __('im_master.completeness.evidence');
        }

        return $errors;
    }

    /** @param list<int> $reviewerIds */
    public function submit(ImRevision $revision, array $reviewerIds, User $approver, User $preparer): ImRevision
    {
        $reviewers = User::query()->whereIn('id', $reviewerIds)->get();

        DB::transaction(function () use ($revision, $reviewers, $approver, $preparer) {
            $round = $revision->review_round;

            ImSignoff::query()->where('revision_id', $revision->id)->where('round', $round)->delete();

            ImSignoff::create([
                'revision_id' => $revision->id, 'round' => $round, 'stage' => 'prepared',
                'role_label' => 'Prepared by', 'user_id' => $preparer->id,
                'user_name' => (string) ($preparer->name ?: $preparer->email),
                'status' => 'Signed', 'signed_at' => Carbon::now(),
            ]);

            foreach ($reviewers as $reviewer) {
                ImSignoff::create([
                    'revision_id' => $revision->id, 'round' => $round, 'stage' => 'review',
                    'role_label' => 'Review by', 'user_id' => $reviewer->id,
                    'user_name' => (string) ($reviewer->name ?: $reviewer->email), 'status' => 'Pending',
                ]);
            }

            ImSignoff::create([
                'revision_id' => $revision->id, 'round' => $round, 'stage' => 'approved',
                'role_label' => 'Approved by', 'user_id' => $approver->id,
                'user_name' => (string) ($approver->name ?: $approver->email), 'status' => 'Pending',
            ]);

            $revision->status = 'In Review';
            $revision->prepared_by_user_id = $preparer->id;
            $revision->prepared_by_name = (string) ($preparer->name ?: $preparer->email);
            $revision->approver_user_id = $approver->id;
            $revision->pending_with = $reviewers->map(fn (User $u) => (string) ($u->name ?: $u->email))->implode(', ');
            $revision->save();

            ImLog::write($preparer, 'SUBMIT', $revision, ['round' => $round, 'reviewers' => $reviewers->pluck('email')->all(), 'approver' => $approver->email]);
        });

        foreach ($reviewers as $reviewer) {
            (new CreateNotification)([
                'user_id' => $reviewer->id,
                'title' => 'IM Waiting for QA Review',
                'message' => "IM {$revision->im_number} menunggu review QA Anda.",
                'type' => 'review',
            ]);
        }

        return $revision->fresh() ?? $revision;
    }

    public function review(ImSignoff $signoff, string $comment, User $reviewer): ImRevision
    {
        $revision = $signoff->revision;
        if ($revision === null) {
            throw new \RuntimeException('Sign-off without a revision.');
        }

        DB::transaction(function () use ($signoff, $revision, $comment, $reviewer) {
            $signoff->status = 'Signed';
            $signoff->comment = $comment;
            $signoff->signed_at = Carbon::now();
            $signoff->save();

            $pending = ImSignoff::query()
                ->where('revision_id', $revision->id)->where('round', $signoff->round)
                ->where('stage', 'review')->where('status', 'Pending')
                ->pluck('user_name')->all();

            if ($pending === []) {
                $approver = $revision->approver_user_id ? User::query()->find($revision->approver_user_id) : null;
                $revision->status = 'Ready for Approval';
                $revision->pending_with = $approver ? (string) ($approver->name ?: $approver->email) : 'QA';
            } else {
                $revision->pending_with = implode(', ', $pending);
            }
            $revision->save();

            ImLog::write($reviewer, 'REVIEW', $revision, ['remaining' => $pending]);
        });

        $revision = $revision->fresh() ?? $revision;

        if ($revision->status === 'Ready for Approval') {
            (new CreateNotification)([
                'user_id' => $revision->approver_user_id,
                'title' => 'IM Waiting QA Approval',
                'message' => "IM {$revision->im_number} menunggu approval QA Anda.",
                'type' => 'approval',
            ]);
        }

        return $revision;
    }

    /** @param 'Approved'|'Need Revision'|'Rejected' $decision */
    public function decide(ImRevision $revision, string $decision, string $comment, User $approver): ImRevision
    {
        $name = (string) ($approver->name ?: $approver->email);

        DB::transaction(function () use ($revision, $decision, $comment, $name, $approver) {
            $now = Carbon::now();

            ImSignoff::query()
                ->where('revision_id', $revision->id)->where('round', $revision->review_round)->where('stage', 'approved')
                ->update([
                    'status' => $decision === 'Approved' ? 'Signed' : 'Returned',
                    'comment' => $comment, 'signed_at' => $now,
                    'user_id' => $approver->id, 'user_name' => $name,
                ]);

            $revision->approval_comment = $comment;
            $revision->pending_with = '';

            if ($decision === 'Approved') {
                $revision->approved_by = $name;
                $revision->approved_at = $now;
                $revision->save();
                $this->release->afterApproval($revision, $approver);
            } elseif ($decision === 'Need Revision') {
                $revision->status = 'Need Revision';
                $revision->pending_with = 'QA';
                $revision->review_round = $revision->review_round + 1;
                $revision->rejected_by = $name;
                $revision->rejected_at = $now;
                $revision->save();
            } else {
                $revision->status = 'Rejected';
                $revision->rejected_by = $name;
                $revision->rejected_at = $now;
                $revision->save();

                if ($revision->im_number_id !== null) {
                    ImNumber::query()->whereKey($revision->im_number_id)->update(['status' => 'Void', 'void_reason' => 'IM ditolak QA']);
                }
            }

            ImLog::write($approver, strtoupper(str_replace(' ', '_', $decision)), $revision, ['comment' => $comment]);
        });

        $revision = $revision->fresh() ?? $revision;

        if ($revision->prepared_by_user_id !== null) {
            (new CreateNotification)([
                'user_id' => $revision->prepared_by_user_id,
                'title' => 'IM '.$decision,
                'message' => "IM {$revision->im_number}: {$decision}.",
                'type' => 'info',
            ]);
        }

        return $revision;
    }
}
