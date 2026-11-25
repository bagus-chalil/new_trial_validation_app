<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Trial Validation v2.0 Case: the parent number (VAL-NPD-2611-001) every
 * other validation document points to by id. See ../../TRIAL_VALIDATION_V2_PLAN.md.
 *
 * @property int $id
 * @property string $case_no
 * @property string $case_type
 * @property string $period
 * @property int $seq
 * @property int|null $product_id
 * @property string $product_name
 * @property string $source
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 * @property-read Product|null $product
 */
class ValidationCase extends Model
{
    protected $table = 'vd_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return HasMany<ValidationCaseLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(ValidationCaseLink::class, 'case_id');
    }
}
