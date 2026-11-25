<?php

namespace App\Services\Validation;

use App\Models\Trial;

/**
 * "One trial" and its derived Trial Result (TRIAL_VALIDATION_V2_PLAN.md
 * section 3, decision D4).
 *
 * A trial counts as an attempt once it has been submitted for review: not a
 * Draft and not soft-deleted. Rejected and Re-trial attempts count too, they
 * were really performed.
 *
 * Result (derived, never stored):
 * - Pass: Approved and no NOT OK parameter.
 * - Pass with Approval Client: Approved, has NOT OK, and the client approval
 *   evidence is recorded.
 * - Re-trial: Need Revision, or Approved with NOT OK and no client approval.
 * - Fail: Rejected.
 * - Pending: still In Review / Ready for Approval, no result yet.
 */
class TrialOutcome
{
    public const PASS = 'Pass';

    public const PASS_CLIENT = 'Pass with Approval Client';

    public const RETRIAL = 'Re-trial';

    public const FAIL = 'Fail';

    public const PENDING = 'Pending';

    public static function isAttempt(Trial $trial): bool
    {
        return $trial->deleted_at === null && $trial->progress_status !== 'Draft';
    }

    public static function result(Trial $trial, bool $hasNotOk, bool $clientApproved): string
    {
        if ($trial->progress_status === 'Rejected' || $trial->final_decision === 'Rejected') {
            return self::FAIL;
        }

        if ($trial->progress_status === 'Need Revision') {
            return self::RETRIAL;
        }

        if ($trial->progress_status === 'Approved') {
            if (! $hasNotOk) {
                return self::PASS;
            }

            return $clientApproved ? self::PASS_CLIENT : self::RETRIAL;
        }

        return self::PENDING;
    }
}
