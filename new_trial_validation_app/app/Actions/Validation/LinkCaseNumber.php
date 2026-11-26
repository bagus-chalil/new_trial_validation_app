<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\ImNumber;
use App\Models\QcoNumber;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseLink;
use Illuminate\Validation\ValidationException;

/**
 * Records an issued QCO / IM number on a Case through vd_case_links (kind
 * `qco` / `im`, ref_key = the number). A number belongs to one Case only
 * (unique kind + ref_key). The manual QCO field on the Assessment keeps
 * working; this is the register-backed path beside it.
 */
class LinkCaseNumber
{
    public function link(ValidationCase $case, QcoNumber|ImNumber $number, User $user): ValidationCaseLink
    {
        $kind = $number instanceof QcoNumber ? 'qco' : 'im';

        $existing = ValidationCaseLink::query()->where('kind', $kind)->where('ref_key', $number->number)->first();
        if ($existing !== null) {
            if ((int) $existing->case_id === $case->id) {
                return $existing;
            }

            throw ValidationException::withMessages(['number' => __('numbers.number.already_linked')]);
        }

        $link = ValidationCaseLink::create([
            'case_id' => $case->id,
            'kind' => $kind,
            'ref_id' => $number->id,
            'ref_key' => $number->number,
            'linked_by' => $user->id,
        ]);

        $this->log($user, 'LINK_'.strtoupper($kind), $case, $number->number);

        return $link;
    }

    public function unlink(ValidationCase $case, string $kind, string $refKey, User $user): void
    {
        $deleted = ValidationCaseLink::query()
            ->where('case_id', $case->id)
            ->where('kind', $kind)
            ->where('ref_key', $refKey)
            ->delete();

        if ($deleted > 0) {
            $this->log($user, 'UNLINK_'.strtoupper($kind), $case, $refKey);
        }
    }

    private function log(User $user, string $action, ValidationCase $case, string $number): void
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
            'new_data' => json_encode(['number' => $number]),
        ]);
    }
}
