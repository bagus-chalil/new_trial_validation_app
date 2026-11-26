<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An issued QCO number (PRNI). See ../../TRIAL_VALIDATION_V2_PLAN.md Tahap 3.
 *
 * @property int $id
 * @property string $number
 * @property int $year
 * @property int $seq
 * @property int|null $product_id
 * @property string $product_name
 * @property string $client
 * @property string|null $sales_month
 * @property int|null $qty_po
 * @property string|null $notes
 * @property string $status
 * @property string|null $void_reason
 * @property string $issued_by_name
 */
class QcoNumber extends Model
{
    protected $table = 'vd_qco_numbers';

    protected $guarded = [];
}
