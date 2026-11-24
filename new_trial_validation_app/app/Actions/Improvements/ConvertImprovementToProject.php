<?php

namespace App\Actions\Improvements;

use App\Actions\Projects\SaveProject;
use App\Models\Improvement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "To Project": creates a Project pre-filled from the improvement and links it
 * back through `pm_improvements.project_id`. One improvement becomes at most
 * one project.
 */
class ConvertImprovementToProject
{
    public function __construct(
        private readonly SaveProject $saveProject,
        private readonly LogImprovementActivity $log,
    ) {}

    public function __invoke(Improvement $improvement, User $user): Project
    {
        if ($improvement->project_id && Project::whereNull('deleted_at')->whereKey($improvement->project_id)->exists()) {
            throw new InvalidArgumentException(__('improvements.messages.already_converted'));
        }

        return DB::transaction(function () use ($improvement, $user) {
            // PIC is the submitter when still an active user, else the converter.
            $picId = $user->id;

            if ($improvement->submitted_by_user_id
                && User::query()->where('is_active', 1)->whereNull('deleted_at')->whereKey($improvement->submitted_by_user_id)->exists()) {
                $picId = (int) $improvement->submitted_by_user_id;
            }

            $description = collect([
                __('improvements.fields.issue') => $improvement->issue,
                __('improvements.fields.background') => $improvement->background,
                __('improvements.fields.purpose') => $improvement->purpose,
                __('improvements.fields.goals') => $improvement->goals,
            ])->filter(fn ($v) => filled($v))
                ->map(fn ($v, $k) => $k.': '.trim((string) $v))
                ->implode("\n\n");

            $project = ($this->saveProject)(null, [
                'name' => $improvement->title,
                'category' => 'Improvement',
                'status' => 'Not Started',
                'priority' => 'Medium',
                'pic_user_id' => $picId,
                'area' => $improvement->department,
                'description' => $description !== '' ? $description : null,
            ], $user);

            $old = $improvement->only(['status', 'decision', 'project_id']);
            $improvement->fill([
                'project_id' => $project->id,
                'decision' => 'To Project',
                'status' => 'Disetujui',
                'updated_by_name' => $user->name,
            ])->save();

            ($this->log)($user, 'CONVERT', $improvement, $old, ['project_id' => $project->id]);

            return $project;
        });
    }
}
