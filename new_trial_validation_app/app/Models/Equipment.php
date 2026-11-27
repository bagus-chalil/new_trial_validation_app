<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One machine/equipment of a line (Equipment List). `equipment_code`
 * ({line_code}-E{nn}) is generated once and never changes.
 *
 * @property int $id
 * @property string $equipment_code
 * @property int $line_id
 * @property int|null $deleted_by
 */
class Equipment extends Model
{
    use SoftDeletes;

    public const TYPES = ['Mixing', 'Milling', 'Melting / Heating', 'Filling', 'Pouring / Moulding', 'Pressing', 'Cooling', 'Capping / Assembly', 'Labeling', 'Coding / Printing', 'Packing / Cartoning', 'Inspection', 'Utility', 'Sealing', 'Other'];

    public const CONDITIONS = ['Good', 'Needs Attention', 'Under Maintenance', 'Out of Service', 'To be verified'];

    public const MANUAL_STATUSES = ['Ada', 'Ada (di Produksi)', 'Tidak Ada'];

    protected $table = 'mc_equipment';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rated_capacity' => 'float', 'max_capacity' => 'float'];
    }

    /** @return BelongsTo<MachineLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(MachineLine::class, 'line_id');
    }
}
