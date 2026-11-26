<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Validation Assessment (ADD NEW DATA): the starting point of a Case.
 * See ../../TRIAL_VALIDATION_V2_PLAN.md section 5.
 *
 * @property int $id
 * @property int $case_id
 * @property int|null $product_id
 * @property string $product_name
 * @property Carbon|null $receive_date
 * @property string $client
 * @property string|null $sales_month
 * @property int|null $qty_po
 * @property string $packaging_type
 * @property list<string>|null $validation_scope
 * @property string $recommended_machine
 * @property string $qco_no
 * @property string $qco_link
 * @property Carbon|null $pm_received_date
 * @property bool $pm_na
 * @property Carbon|null $bulk_received_date
 * @property bool $bulk_na
 * @property string $risk_level
 * @property string|null $notes
 * @property-read ValidationCase $case
 * @property-read Product|null $product
 */
class ValidationAssessment extends Model
{
    protected $table = 'vd_assessments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'receive_date' => 'date',
            'pm_received_date' => 'date',
            'bulk_received_date' => 'date',
            'pm_na' => 'boolean',
            'bulk_na' => 'boolean',
            'qty_po' => 'integer',
            'validation_scope' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ValidationCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ValidationCase::class, 'case_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
