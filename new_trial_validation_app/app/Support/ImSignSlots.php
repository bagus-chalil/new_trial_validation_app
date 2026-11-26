<?php

namespace App\Support;

use App\Models\User;

/** Reads config/im_signoff.php: titles of the IM sign-off sheet and who may sign each "Knowledge by" row. */
final class ImSignSlots
{
    /** @return list<string> */
    public static function reviewLabels(): array
    {
        return array_values(array_map('strval', (array) config('im_signoff.review_labels', [])));
    }

    /** @return list<array{label: string, teams: list<string>}> */
    public static function knowledgeSlots(): array
    {
        return array_values(array_map(fn ($s) => [
            'label' => (string) $s['label'],
            'teams' => array_values(array_map(fn ($t) => strtoupper(trim((string) $t)), (array) $s['teams'])),
        ], (array) config('im_signoff.knowledge_slots', [])));
    }

    /** True when the account (not a read-only Viewer) belongs to a team allowed for this slot label. */
    public static function userMaySign(User $user, string $label): bool
    {
        if ($user->effectiveRole() === 'Viewer') {
            return false;
        }

        foreach (self::knowledgeSlots() as $slot) {
            if ($slot['label'] !== $label) {
                continue;
            }
            foreach ([$user->department, $user->review_unit] as $value) {
                if (in_array(strtoupper(trim((string) $value)), $slot['teams'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
