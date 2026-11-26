<?php

namespace App\Actions\ImMaster;

use App\Models\ImMaster;
use App\Models\ImNumber;
use App\Models\ImRevision;
use App\Models\User;
use App\Support\ImLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Release rules of the IM lifecycle: after QA approval a revision becomes
 * Effective on its release date (today or earlier = immediately, later =
 * Scheduled until `validation:im-release` promotes it). Promoting supersedes
 * the previously Effective revision, so a master never has two Effective
 * revisions. Runs already pinned to the old revision keep their snapshot.
 */
class ImRelease
{
    /** Called right after QA approval. */
    public function afterApproval(ImRevision $revision, User $by): ImRevision
    {
        $due = $revision->effective_from === null || $revision->effective_from->toDateString() <= now()->toDateString();

        if ($due) {
            $this->promote($revision, $by);
        } else {
            $revision->status = 'Scheduled';
            $revision->save();
            ImLog::write($by, 'SCHEDULE', $revision, ['effective_from' => $revision->effective_from->toDateString()]);
        }

        return $revision->fresh() ?? $revision;
    }

    public function promote(ImRevision $revision, ?User $by = null): void
    {
        DB::transaction(function () use ($revision, $by) {
            ImMaster::query()->lockForUpdate()->findOrFail($revision->master_id);

            $previous = ImRevision::query()
                ->where('master_id', $revision->master_id)
                ->where('status', 'Effective')
                ->where('id', '!=', $revision->id)
                ->get();

            foreach ($previous as $old) {
                $old->status = 'Superseded';
                $old->superseded_at = Carbon::now();
                $old->save();

                if ($old->im_number_id !== null) {
                    ImNumber::query()->whereKey($old->im_number_id)->update(['status' => 'Superseded']);
                }
            }

            $revision->status = 'Effective';
            $revision->effective_at = Carbon::now();
            $revision->pending_with = '';
            $revision->save();

            if ($revision->im_number_id !== null) {
                ImNumber::query()->whereKey($revision->im_number_id)->update(['status' => 'Active']);
            }

            if ($by !== null) {
                ImLog::write($by, 'EFFECTIVE', $revision, ['superseded' => $previous->pluck('im_number')->all()]);
            }
        });
    }

    /** Promotes every Scheduled revision whose release date has come. */
    public function releaseDue(): int
    {
        $count = 0;

        ImRevision::query()
            ->where('status', 'Scheduled')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->orderBy('effective_from')->orderBy('revision_no')
            ->get()
            ->each(function (ImRevision $rev) use (&$count) {
                $this->promote($rev);
                $count++;
            });

        return $count;
    }
}
