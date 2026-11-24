<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $po_no
 * @property Carbon|null $po_date
 * @property string $vendor_name
 * @property string $currency
 * @property string $tax_percent
 * @property bool $use_signature
 */
class PurchaseOrder extends Model
{
    protected $table = 'pm_purchase_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'tax_percent' => 'decimal:2',
            'use_signature' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id')->orderBy('sort_order')->orderBy('id');
    }

    /** @return array{sub: float, tax: float, total: float} */
    public function totals(): array
    {
        $sub = 0.0;
        foreach ($this->items as $item) {
            $sub += (float) $item->qty * (float) $item->unit_price;
        }
        $tax = round($sub * (float) $this->tax_percent / 100);

        return ['sub' => $sub, 'tax' => $tax, 'total' => $sub + $tax];
    }
}
