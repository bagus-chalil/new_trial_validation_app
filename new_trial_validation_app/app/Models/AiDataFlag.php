<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Side-table label on a row of a legacy table (see the migration for why it
 * is a side table). Consumed by the AI export to drop/blank low-quality data.
 *
 * @property int $id
 * @property string $source_table
 * @property string $source_id
 * @property string|null $field
 * @property int|null $trial_id
 * @property string $flag
 * @property string|null $reason
 * @property string $detected_by
 * @property Carbon|null $created_at
 */
#[Fillable(['source_table', 'source_id', 'field', 'trial_id', 'flag', 'reason', 'detected_by'])]
class AiDataFlag extends Model
{
    public const JUNK_TEXT = 'junk_text';

    public const SUSPECTED_TEST_TRIAL = 'suspected_test_trial';

    protected $table = 'ai_data_flags';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
