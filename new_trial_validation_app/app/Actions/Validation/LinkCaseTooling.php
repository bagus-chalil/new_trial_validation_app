<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\Tooling;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use Illuminate\Validation\ValidationException;

/**
 * Ties a Check Tooling request to a Case through vd_case_links (kind
 * `tooling`). Never writes to pm_toolings: `npd_no` stays as the old
 * reference. A tooling belongs to one Case only (unique kind + ref_key).
 */
class LinkCaseTooling
{
    public function link(ValidationCase $case, Tooling $tooling, User $user): ValidationCaseLink
    {
        $existing = ValidationCaseLink::query()
            ->where('kind', 'tooling')
            ->where('ref_key', (string) $tooling->id)
            ->first();

        if ($existing !== null) {
            if ((int) $existing->case_id === $case->id) {
                return $existing;
            }

            throw ValidationException::withMessages(['tooling_id' => __('assessment.tooling.already_linked')]);
        }

        $link = ValidationCaseLink::create([
            'case_id' => $case->id,
            'kind' => 'tooling',
            'ref_id' => $tooling->id,
            'ref_key' => (string) $tooling->id,
            'linked_by' => $user->id,
        ]);

        $this->log($user, 'LINK_TOOLING', $case, $tooling);

        return $link;
    }

    public function unlink(ValidationCase $case, Tooling $tooling, User $user): void
    {
        $deleted = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', 'tooling')
            ->where('ref_key', (string) $tooling->id)
            ->delete();

        if ($deleted > 0) {
            $this->log($user, 'UNLINK_TOOLING', $case, $tooling);
        }
    }

    private function log(User $user, string $action, ValidationCase $case, Tooling $tooling): void
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
            'new_data' => json_encode(['tooling_id' => $tooling->id, 'npd_no' => $tooling->npd_no]),
        ]);
    }
}
