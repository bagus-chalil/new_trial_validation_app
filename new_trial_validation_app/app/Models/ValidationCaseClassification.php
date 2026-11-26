<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Brief 02: process area, purpose, subject and document type are separate
 * axes. One row per Case, stored beside vd_cases (the Case number prefix
 * NPD/IMP/MIX is never renamed, see plan D10).
 *
 * @property int $id
 * @property int $case_id
 * @property string|null $process_area
 * @property string|null $purpose
 * @property string|null $subject
 * @property string|null $document_type
 * @property string|null $other_purpose_note
 * @property int|null $updated_by_user_id
 */
class ValidationCaseClassification extends Model
{
    protected $table = 'vd_case_classifications';

    protected $guarded = [];
}
