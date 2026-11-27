<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A production line / mixer from the Line Master. `line_code` (SC-01, MX-SC01)
 * is the permanent trace key IPC references.
 *
 * @property int $id
 * @property string $line_code
 * @property int|null $deleted_by
 */
class MachineLine extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['Skincare', 'Make Up', 'Fragrance', 'Mixing', 'Bignose', 'Packing Equipment'];

    public const STATUSES = ['Active', 'Idle', 'Under Maintenance', 'Under Installation', 'To be verified'];

    public const AUTOMATION = ['Manual', 'Semi-automatic', 'Automatic'];

    public const CAPACITY_UNITS = ['pcs/hour', 'pcs/min', 'kg/hour', 'L/hour', 'kg/batch', 'L/batch'];

    protected $table = 'mc_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rated_capacity' => 'float'];
    }

    /** @return BelongsTo<Plant, $this> */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /** @return HasMany<Equipment, $this> */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'line_id')->orderBy('process_step');
    }
}
