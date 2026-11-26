<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\Project;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use Illuminate\Validation\ValidationException;

/**
 * Ties an Improvement to a Case through vd_case_links (kind `improvement`).
 * One Case per Improvement. When the Improvement already became a Project,
 * the Project is tied to the same Case (kind `project`), and
 * ConvertImprovementToProject does the same when it happens later.
 * pm_improvements is never written.
 */
class LinkCaseImprovement
{
    public function link(ValidationCase $case, Improvement $improvement, User $user): ValidationCaseLink
    {
        $existing = ValidationCaseLink::query()
            ->where('kind', 'improvement')
            ->where('ref_key', (string) $improvement->id)
            ->first();

        if ($existing !== null) {
            if ((int) $existing->case_id === $case->id) {
                return $existing;
            }

            throw ValidationException::withMessages(['improvement_id' => __('assessment.improvement.already_linked')]);
        }

        $link = ValidationCaseLink::create([
            'case_id' => $case->id,
            'kind' => 'improvement',
            'ref_id' => $improvement->id,
            'ref_key' => (string) $improvement->id,
            'linked_by' => $user->id,
        ]);

        if ($improvement->project_id !== null) {
            $this->inheritProject($case, (int) $improvement->project_id, $user->id);
        }

        $this->log($user, 'LINK_IMPROVEMENT', $case, $improvement);

        return $link;
    }

    public function unlink(ValidationCase $case, Improvement $improvement, User $user): void
    {
        $deleted = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', 'improvement')
            ->where('ref_key', (string) $improvement->id)
            ->delete();

        if ($deleted > 0) {
            $this->log($user, 'UNLINK_IMPROVEMENT', $case, $improvement);
        }
    }

    /**
     * The Project made from a linked Improvement joins its Case. Never moves a
     * Project that is already tied to a (possibly different) Case.
     */
    public function inheritProject(ValidationCase $case, int $projectId, ?int $userId): void
    {
        if (! Project::query()->whereNull('deleted_at')->whereKey($projectId)->exists()) {
            return;
        }

        ValidationCaseLink::query()->firstOrCreate(
            ['kind' => 'project', 'ref_key' => (string) $projectId],
            ['case_id' => $case->id, 'ref_id' => $projectId, 'linked_by' => $userId],
        );
    }

    /** Called after "To Project": inherit the Case link, if the Improvement has one. */
    public function inheritForConvertedProject(Improvement $improvement, Project $project, ?int $userId): void
    {
        $link = ValidationCaseLink::query()
            ->where('kind', 'improvement')
            ->where('ref_key', (string) $improvement->id)
            ->first();

        if ($link === null) {
            return;
        }

        $case = ValidationCase::query()->whereNull('deleted_at')->find($link->case_id);
        if ($case !== null) {
            $this->inheritProject($case, $project->id, $userId);
        }
    }

    private function log(User $user, string $action, ValidationCase $case, Improvement $improvement): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'VALIDATION_CASE',
            'record_id' => (string) $case->id,
            'record_label' => $case->case_no,
            'old_data' => null,
            'new_data' => json_encode(['improvement_id' => $improvement->id, 'title' => $improvement->title]),
        ]);
    }
}
