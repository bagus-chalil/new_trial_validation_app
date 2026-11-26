<?php

namespace App\Support;

/**
 * Wizard steps per Case kind (Tahap 4). The Trial wizard keeps its own six
 * steps (Header, Validation, Weighing Packaging, Weighing Filling,
 * Attachments, Review); Mixing has no Weighing. The frontend mirrors this in
 * resources/js/lib/validation-steps.ts.
 */
final class ValidationStepConfig
{
    public const TRIAL = ['header', 'validation', 'weighing-packaging', 'weighing-filling', 'attachments', 'review'];

    public const MIXING = ['header', 'validation', 'attachments', 'review'];

    /** @return list<string> */
    public static function forKind(string $kind): array
    {
        return $kind === 'mixing' ? self::MIXING : self::TRIAL;
    }
}
