<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $improvement_id
 * @property string $stored_name
 * @property bool $is_missing
 * @property string $phase before (proposal attachment) or after (result evidence)
 * @property Carbon|null $uploaded_at
 */
class ImprovementFile extends Model
{
    protected $table = 'pm_improvement_files';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'is_missing' => 'boolean',
        ];
    }

    /** @return BelongsTo<Improvement, $this> */
    public function improvement(): BelongsTo
    {
        return $this->belongsTo(Improvement::class, 'improvement_id');
    }
}
