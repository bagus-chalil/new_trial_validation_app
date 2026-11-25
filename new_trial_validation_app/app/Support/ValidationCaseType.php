<?php

namespace App\Support;

/**
 * Case type (the NPD / IMP / MIX part of VAL-NPD-2611-001) for a trial,
 * from the mapping the user fixed on 2026-11-25 (TRIAL_VALIDATION_V2_PLAN.md
 * 8.5): product_type "Mixing" wins and gives MIX; a validation_category whose
 * name contains "NPD" gives NPD; everything else (Validation, MPT, Filling,
 * Rework, ...) is an Improvement (IMP).
 */
final class ValidationCaseType
{
    public const NPD = 'NPD';

    public const IMP = 'IMP';

    public const MIX = 'MIX';

    public const ALL = [self::NPD, self::IMP, self::MIX];

    public static function forTrial(?string $validationCategory, ?string $productType): string
    {
        if (mb_strtolower(trim((string) $productType)) === 'mixing') {
            return self::MIX;
        }

        if (str_contains(mb_strtoupper((string) $validationCategory), 'NPD')) {
            return self::NPD;
        }

        return self::IMP;
    }
}
