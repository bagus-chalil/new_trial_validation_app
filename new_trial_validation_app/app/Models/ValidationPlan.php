<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Weekly trial schedule request from PIE to PPIC (SKU + week).
 *
 * @property int $id
 * @property int $case_id
 * @property int|null $product_id
 * @property string $product_name
 * @property Carbon $week_start
 * @property string $status
 * @property string|null $notes
 * @property string|null $ppic_note
 * @property string $requested_by_name
 * @property string $confirmed_by_name
 * @property Carbon|null $confirmed_at
 * @property-read ValidationCase $case
 */
class ValidationPlan extends Model
{
    public const STATUSES = ['Requested', 'Confirmed', 'Cancelled'];

    protected $table = 'vd_validation_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'confirmed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ValidationCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ValidationCase::class, 'case_id');
    }
}
