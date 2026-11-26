<?php

namespace App\Support;

use App\Models\ValidationCase;
use App\Models\ValidationCaseClassification;

/**
 * The four classification axes of a Case (brief 02) and the defaults derived
 * from the Case type. A stored row always wins over the derived default.
 */
final class CaseClassification
{
    public const PROCESS_AREAS = ['Mixing', 'Filling-Packing'];

    public const PURPOSES = ['NPD', 'Improvement', 'Other'];

    public const SUBJECTS = ['Product', 'Machine', 'RM', 'PM', 'Process'];

    public const DOCUMENT_TYPES = ['Validation Plan', 'Trial Report', 'Line Configuration'];

    /** @return array{process_area: string, purpose: string, subject: string, document_type: string|null, other_purpose_note: string|null, derived: bool} */
    public static function forCase(ValidationCase $case): array
    {
        $stored = ValidationCaseClassification::query()->where('case_id', $case->id)->first();

        $default = self::defaultsFor($case->case_type);

        return [
            'process_area' => $stored->process_area ?? $default['process_area'],
            'purpose' => $stored->purpose ?? $default['purpose'],
            'subject' => $stored->subject ?? $default['subject'],
            'document_type' => $stored->document_type ?? null,
            'other_purpose_note' => $stored->other_purpose_note ?? null,
            'derived' => $stored === null,
        ];
    }

    /** @return array{process_area: string, purpose: string, subject: string} */
    public static function defaultsFor(string $caseType): array
    {
        return [
            'process_area' => $caseType === ValidationCaseType::MIX ? 'Mixing' : 'Filling-Packing',
            'purpose' => $caseType === ValidationCaseType::NPD ? 'NPD' : 'Improvement',
            'subject' => 'Product',
        ];
    }
}
