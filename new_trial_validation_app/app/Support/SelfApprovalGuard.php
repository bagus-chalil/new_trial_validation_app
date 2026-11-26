<?php

namespace App\Support;

use App\Models\User;

/**
 * Brief 02 approval control: a person never reviews, signs or approves a
 * document they prepared. Applies to Admin as well; switch off with
 * VALIDATION_PREVENT_SELF_APPROVAL=false only in a single-person test setup.
 */
final class SelfApprovalGuard
{
    public static function enabled(): bool
    {
        return (bool) config('validation_v2.prevent_self_approval', true);
    }

    /** True when $user is the person who prepared the document (owner e-mail). */
    public static function isPreparerByEmail(User $user, ?string $createdByEmail): bool
    {
        return self::enabled()
            && trim((string) $createdByEmail) !== ''
            && strcasecmp(trim((string) $createdByEmail), trim((string) $user->email)) === 0;
    }

    /** True when $user is the person who last prepared the document (user id). */
    public static function isPreparerById(User $user, ?int $preparerUserId): bool
    {
        return self::enabled() && $preparerUserId !== null && $preparerUserId === (int) $user->id;
    }
}
