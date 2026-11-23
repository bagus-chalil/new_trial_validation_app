<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Projects module Fase 4: links a Trial to a Validation stage (or removes the
 * link). Linking immediately syncs the stage from the trial's status. Callers
 * check ProjectPolicy::update() on the project; this action enforces the
 * data rules (Validation stage only, one trial per stage, one stage per trial).
 */
class LinkProjectStageTrial
{
    public function __construct(
        private readonly LogProjectActivity $log,
        private readonly SyncProjectStagesFromTrial $sync,
    ) {}

    /**
     * Throws a ValidationException (key `trial_id`) when the stage can't take
     * a trial. Used before creating a draft trial from a stage too, so no
     * orphan trial is created for a stage that can't be linked.
     */
    public static function assertLinkable(ProjectStage $stage, ?int $ignoreTrialId = null): void
    {
        if ($stage->stage_type !== 'Validation') {
            throw ValidationException::withMessages(['trial_id' => __('projects.trial_link.not_validation')]);
        }

        if ($stage->trial_id !== null && (int) $stage->trial_id !== $ignoreTrialId) {
            throw ValidationException::withMessages(['trial_id' => __('projects.trial_link.already_linked')]);
        }
    }

    public function link(Project $project, ProjectStage $stage, Trial $trial, User $user): ProjectStage
    {
        self::assertLinkable($stage);

        $takenBy = ProjectStage::query()->where('trial_id', $trial->id)->where('id', '!=', $stage->id)->exists();
        if ($takenBy) {
            throw ValidationException::withMessages(['trial_id' => __('projects.trial_link.trial_taken')]);
        }

        DB::transaction(function () use ($project, $stage, $trial, $user) {
            $stage->trial_id = $trial->id;
            $stage->save();

            ($this->log)($user, 'LINK_TRIAL', $project->id, $project->name, null, [
                'stage' => $stage->name,
                'trial' => $trial->trial_code,
            ]);
        });

        // The person linking is acting on the project themselves: no PIC notification.
        ($this->sync)($trial, $user, notify: false);

        return $stage->refresh();
    }

    public function unlink(Project $project, ProjectStage $stage, User $user): ProjectStage
    {
        $trialCode = $stage->trial?->trial_code;

        $stage->trial_id = null;
        $stage->save();

        ($this->log)($user, 'UNLINK_TRIAL', $project->id, $project->name, [
            'stage' => $stage->name,
            'trial' => $trialCode,
        ], null);

        return $stage;
    }
}
