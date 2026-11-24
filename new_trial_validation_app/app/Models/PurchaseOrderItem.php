<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $sort_order
 * @property string $description
 * @property string $qty
 * @property string $unit
 * @property string $unit_price
 * @property Carbon|null $due_date
 */
class PurchaseOrderItem extends Model
{
    protected $table = 'pm_purchase_order_items';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'due_date' => 'date',
        ];
    }
}
