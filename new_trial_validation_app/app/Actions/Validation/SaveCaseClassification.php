<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\ValidationCase;
use App\Models\ValidationCaseClassification;
use App\Support\CaseClassification;

/** Stores the four classification axes of a Case (never renames the Case number). */
class SaveCaseClassification
{
    /** @param array{process_area: string, purpose: string, subject: string, document_type?: string|null, other_purpose_note?: string|null} $data */
    public function __invoke(ValidationCase $case, array $data, User $user): ValidationCaseClassification
    {
        $before = CaseClassification::forCase($case);

        $row = ValidationCaseClassification::query()->firstOrNew(['case_id' => $case->id]);
        $row->process_area = $data['process_area'];
        $row->purpose = $data['purpose'];
        $row->subject = $data['subject'];
        $row->document_type = $data['document_type'] ?? null;
        $row->other_purpose_note = $data['purpose'] === 'Other' ? ($data['other_purpose_note'] ?? null) : null;
        $row->updated_by_user_id = $user->id;
        $row->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'UPDATE',
            'module' => 'VALIDATION_CASE',
            'record_id' => (string) $case->id,
            'record_label' => $case->case_no,
            'old_data' => json_encode($before),
            'new_data' => json_encode(CaseClassification::forCase($case)),
        ]);

        return $row;
    }
}
