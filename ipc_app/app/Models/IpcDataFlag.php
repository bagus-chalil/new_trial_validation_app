<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Side-table label on a row of another table ("this value is unfit for AI training / analysis").
 * Never deletes or alters the labelled row; a future export must skip flagged rows via
 * excludedIds()/isFlagged().
 *
 * @property int $id
 * @property string $source_table
 * @property string $source_id
 * @property string|null $field
 * @property string $flag
 * @property string|null $reason
 * @property string $detected_by
 */
class IpcDataFlag extends Model
{
    public const BAD_NUMBER_FORMAT = 'bad_number_format';

    protected $table = 'ipc_data_flags';

    protected $fillable = ['source_table', 'source_id', 'field', 'flag', 'reason', 'detected_by'];

    const UPDATED_AT = null;

    /**
     * Ids of $table rows carrying any flag (optionally one specific flag) — what an export must skip.
     *
     * @return list<string>
     */
    public static function excludedIds(string $table, ?string $flag = null): array
    {
        return static::query()
            ->where('source_table', $table)
            ->when($flag !== null, fn ($q) => $q->where('flag', $flag))
            ->distinct()
            ->pluck('source_id')
            ->all();
    }
}
