<?php

namespace App\Actions\Projects;

use App\Actions\Notifications\CreateNotification;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Projects module Fase 4: a Validation stage linked to a Trial
 * (`pm_stages.trial_id`) follows that trial's status. Called right after a
 * trial changes status (submit for review, last department review, approval
 * decision) and when a trial is linked to a stage. No polling.
 *
 * The trial is the source of truth for a linked stage's progress:
 *
 * | Trial status        | Stage status            | Progress |
 * |---------------------|-------------------------|----------|
 * | Draft               | On Progress             | 20       |
 * | Need Revision       | On Progress             | 40       |
 * | In Review           | On Progress             | 60       |
 * | Ready for Approval  | On Progress             | 80       |
 * | Approved            | Completed               | 100      |
 * | Rejected            | unchanged (shown as a trial badge on the stage) |
 *
 * Actual start is filled from the trial's creation date and actual end from
 * the approval date when they are still empty; hand-entered dates are kept.
 */
class SyncProjectStagesFromTrial
{
    /** @var array<string, int> */
    public const PROGRESS_BY_STATUS = [
        'Draft' => 20,
        'Need Revision' => 40,
        'In Review' => 60,
        'Ready for Approval' => 80,
        'Approved' => 100,
    ];

    public function __construct(
        private readonly RecalculateProjectProgress $recalculate,
        private readonly CreateNotification $notify,
    ) {}

    /**
     * Same as __invoke() but never throws: the trial workflow must never fail
     * because of the Projects module (e.g. pm_* tables not migrated yet).
     */
    public static function safely(Trial $trial, ?User $actor, bool $notify = true): void
    {
        try {
            if (! Schema::hasTable('pm_stages')) {
                return;
            }

            app(self::class)($trial, $actor, $notify);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return int number of stages that changed
     */
    public function __invoke(Trial $trial, ?User $actor, bool $notify = true): int
    {
        $stages = ProjectStage::query()->where('trial_id', $trial->id)->get();
        $changed = 0;

        foreach ($stages as $stage) {
            $project = Project::query()->whereNull('deleted_at')->find($stage->project_id);

            if (! $project) {
                continue;
            }

            if ($this->apply($stage, $trial)) {
                $old = $stage->getOriginal();
                $stage->save();
                $changed++;

                $this->log($actor, $project, $stage, $trial, [
                    'status' => $old['status'] ?? null,
                    'progress' => $old['progress'] ?? null,
                ]);
                ($this->recalculate)($project);
            }

            if ($notify) {
                $this->notifyPic($project, $stage, $trial, $actor);
            }
        }

        return $changed;
    }

    /** Applies the mapping above to the model in memory; true when something changed. */
    private function apply(ProjectStage $stage, Trial $trial): bool
    {
        $status = (string) $trial->progress_status;

        if (! array_key_exists($status, self::PROGRESS_BY_STATUS)) {
            return false; // Rejected or an unknown legacy value: leave the stage alone.
        }

        $stage->progress = self::PROGRESS_BY_STATUS[$status];
        $stage->status = $status === 'Approved' ? 'Completed' : 'On Progress';

        if ($stage->actual_start === null && $trial->created_at !== null) {
            $stage->actual_start = Carbon::parse($trial->created_at)->startOfDay();
        }

        if ($status === 'Approved' && $stage->actual_end === null) {
            $stage->actual_end = Carbon::parse($trial->approved_at ?? Carbon::now())->startOfDay();
        }

        return $stage->isDirty();
    }

    /**
     * @param  array<string, mixed>  $old
     */
    private function log(?User $actor, Project $project, ProjectStage $stage, Trial $trial, array $old): void
    {
        ActivityLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor !== null ? $actor->name : 'System',
            'user_role' => $actor?->role,
            'action' => 'SYNC_TRIAL_STAGE',
            'module' => 'PROJECT',
            'record_id' => (string) $project->id,
            'record_label' => $project->name,
            'old_data' => json_encode($old),
            'new_data' => json_encode([
                'stage' => $stage->name,
                'trial' => $trial->trial_code,
                'trial_status' => $trial->progress_status,
                'status' => $stage->status,
                'progress' => $stage->progress,
            ]),
        ]);
    }

    private function notifyPic(Project $project, ProjectStage $stage, Trial $trial, ?User $actor): void
    {
        if (! $project->pic_user_id || ($actor !== null && (int) $project->pic_user_id === $actor->id)) {
            return;
        }

        ($this->notify)([
            'user_id' => (int) $project->pic_user_id,
            'trial_id' => $trial->id,
            // Status in the title: CreateNotification skips a duplicate unread
            // row with the same title, so each status change stays visible.
            'title' => "Trial Project: {$trial->progress_status}",
            'message' => "Trial {$trial->trial_code} (stage \"{$stage->name}\" di project {$project->name}) sekarang berstatus {$trial->progress_status}.",
            'type' => 'project',
        ]);
    }
}
