<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Link table row: points one document (trial, project, tooling, ... or a
 * foreign business key such as an IM / QCO number) at a Case. `ref_key` is
 * always set (the id as text, or the foreign key) and unique per kind.
 *
 * @property int $id
 * @property int $case_id
 * @property string $kind
 * @property int|null $ref_id
 * @property string $ref_key
 * @property int|null $attempt_no
 */
class ValidationCaseLink extends Model
{
    public const KINDS = ['trial', 'tooling', 'project', 'line_config', 'mixing', 'ipc_batch', 'im', 'qco'];

    protected $table = 'vd_case_links';

    protected $guarded = [];

    /** @return BelongsTo<ValidationCase, $this> */
    public function validationCase(): BelongsTo
    {
        return $this->belongsTo(ValidationCase::class, 'case_id');
    }
}
